<?php declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\GiaoHangNhanh\Controller\Webhook;

use Secomm\GiaoHangNhanh\Api\Data\TrackInterface;
use Secomm\GiaoHangNhanh\Command\Track\SaveCommand;
use Secomm\GiaoHangNhanh\Helper\OrderStatus;
use Secomm\GiaoHangNhanh\Logger\Logger;
use Secomm\GiaoHangNhanh\Model\Data\TrackDataFactory;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\HTTP\PhpEnvironment\Request as HttpRequest;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Status\HistoryFactory;

class ShippingUpdate implements CsrfAwareActionInterface, HttpPostActionInterface
{
    const STATUS_CODE_SUCCESS = 200;
    const STATUS_CODE_ERROR = 500;
    const STATUS_LABEL = [
        self::STATUS_CODE_SUCCESS => 'Success',
        self::STATUS_CODE_ERROR => 'Error'
    ];
    const MAPPING_STATUS_GHN_MAGENTO = [
        "ready_to_pick" => "ready_to_pick",
        "picking" => "picking",
        "money_collect_picking" => "picking",
        "picked" => "picked",
        "storing" => "transporting",
        "transporting" => "transporting",
        "sorting" => "transporting",
        "delivering" => "delivering",
        "money_collect_delivering" => "delivering",
        "delivery_fail" => "delivery_fail",
        "delivered" => "complete",
        "cancel" => "canceled",
        "waiting_to_return" => "canceled",
        "return" => "canceled",
        "return_transporting" => "canceled",
        "return_sorting" => "canceled",
        "returning" => "canceled",
        "return_fail" => "canceled",
        "returned" => "canceled",
        "exception" => "exception",
        "damage" => "exception",
        "lost" => "exception"
    ];
    const MAPPING_STATE_GHN_MAGENTO = [
        "ready_to_pick" => \Magento\Sales\Model\Order::STATE_PROCESSING,
        "picking" => \Magento\Sales\Model\Order::STATE_PROCESSING,
        "money_collect_picking" => \Magento\Sales\Model\Order::STATE_PROCESSING,
        "picked" => \Magento\Sales\Model\Order::STATE_PROCESSING,
        "storing" => \Magento\Sales\Model\Order::STATE_PROCESSING,
        "transporting" => \Magento\Sales\Model\Order::STATE_PROCESSING,
        "sorting" => \Magento\Sales\Model\Order::STATE_PROCESSING,
        "delivering" => \Magento\Sales\Model\Order::STATE_PROCESSING,
        "money_collect_delivering" => \Magento\Sales\Model\Order::STATE_PROCESSING,
        "delivery_fail" => \Magento\Sales\Model\Order::STATE_PROCESSING,
        "delivered" => \Magento\Sales\Model\Order::STATE_COMPLETE,
        "cancel" => \Magento\Sales\Model\Order::STATE_CLOSED,
        "waiting_to_return" => \Magento\Sales\Model\Order::STATE_CLOSED,
        "return" => \Magento\Sales\Model\Order::STATE_CLOSED,
        "return_transporting" => \Magento\Sales\Model\Order::STATE_CLOSED,
        "return_sorting" => \Magento\Sales\Model\Order::STATE_CLOSED,
        "returning" => \Magento\Sales\Model\Order::STATE_CLOSED,
        "return_fail" => \Magento\Sales\Model\Order::STATE_CLOSED,
        "returned" => \Magento\Sales\Model\Order::STATE_CLOSED,
        "exception" => \Magento\Sales\Model\Order::STATE_CLOSED,
        "damage" => \Magento\Sales\Model\Order::STATE_CLOSED,
        "lost" => \Magento\Sales\Model\Order::STATE_CLOSED
    ];
    const STATUS_NEED_TO_COMMENT = [
        'delivered',
        'exception',
        'damage',
        'lost'
    ];

    /**
     * @param SaveCommand $saveCommand
     * @param TrackDataFactory $trackDataFactory
     * @param Logger $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param HistoryFactory $historyFactory
     * @param JsonFactory $jsonResultFactory
     * @param HttpRequest $request
     * @param JsonSerializer $jsonSerializer
     */
    public function __construct(
        private readonly SaveCommand              $saveCommand,
        private readonly TrackDataFactory         $trackDataFactory,
        private readonly Logger                   $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder    $searchCriteriaBuilder,
        private readonly HistoryFactory           $historyFactory,
        private readonly JsonFactory              $jsonResultFactory,
        private readonly HttpRequest              $request,
        private readonly JsonSerializer           $jsonSerializer
    ) {
    }

    /**
     * Execute action based on request and return result
     *
     * @return Json
     * @throws NotFoundException
     * @throws CouldNotSaveException
     */
    public function execute()
    {
        $rawContent = $this->request->getContent();
        $this->logger->info($rawContent);

        if ($rawContent === null || $rawContent === '') {
            return $this->jsonResultFactory->create()->setData([
                'error' => true,
                'message' => 'Empty request'
            ]);
        }

        $data = $this->jsonSerializer->unserialize($rawContent);
        if (!is_array($data)) {
            return $this->jsonResultFactory->create()->setData([
                'error' => true,
                'message' => 'Invalid JSON'
            ]);
        }

        $trackId = $this->saveGhnTrackModel($data);
        if ($trackId !== 0) {
            return $this->jsonResultFactory->create()->setData(['success' => true]);
        }

        return $this->jsonResultFactory->create()->setData([
            'error' => true,
            'message' => 'Internal error'
        ]);
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @param array $data
     * @return int
     */
    private function saveGhnTrackModel(array $data): int
    {
        try {
            $orderCode = $data['OrderCode'] ?? null;
            if (!$orderCode) {
                return 0;
            }

            $order = $this->getOrderByTrackingCode($orderCode);
            if (!$order) {
                $this->logger->error('Order not found for tracking code: ' . $orderCode);
                return 0;
            }
            $orderId = (int) $order->getEntityId();

            $totalFee = $data['TotalFee'];
            $warehouse = $data['Warehouse'];
            $statusCode = $data['Status'];
            $shopId = $data['ShopID'];
            $valid = $this->validData($data);

            $dateTime = $data['Time'];
            $date = date('Y-m-d', strtotime($dateTime));
            $time = date('H:i:s', strtotime($dateTime));
            $addtionalData = [
                'deliverydate' => $date,
                'deliverytime' => $time,
                'device' => $this->request->getHeader('User-Agent'),
                'ip' => $this->request->getClientIp()
            ];

            $trackDataFactory = $this->trackDataFactory->create();
            $trackDataFactory->setOrderId($orderId);
            $trackDataFactory->setTrackingCode($orderCode);
            $trackDataFactory->setStatusCode($statusCode);
            $trackDataFactory->setStatusLabel(OrderStatus::getStatusDescription($statusCode));
            $trackDataFactory->setWarehouse($warehouse);
            $trackDataFactory->setTotalFee($totalFee);
            $trackDataFactory->setShopId($shopId);
            $trackDataFactory->setAdditionalData($this->jsonSerializer->serialize($addtionalData));
            $trackDataFactory->setRequest($this->jsonSerializer->serialize($data));
            if ($valid) {
                $trackDataFactory->setResultCode(TrackInterface::RESULT_CODE_SUCCESS);
                $order = $this->orderRepository->get($orderId);
                $this->handleOrderStatus($order, $statusCode);
                $this->writeNote($order, $statusCode);
            } else {
                $trackDataFactory->setResultCode(TrackInterface::RESULT_CODE_ERROR);
            }
            $trackDataFactory->setResultLabel(true);
            return $this->saveCommand->execute($trackDataFactory);
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
            return 0;
        }
    }

    /**
     * @param string $trackingCode
     * @return OrderInterface|null
     */
    private function getOrderByTrackingCode(string $trackingCode): ?OrderInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('tracking_code', $trackingCode)
            ->setPageSize(1)
            ->create();
        $orders = $this->orderRepository->getList($searchCriteria)->getItems();
        return !empty($orders) ? reset($orders) : null;
    }

    /**
     * @param array $data
     * @return bool
     */
    private function validData(array $data): bool
    {
        return isset($data['OrderCode'])
            && isset($data['TotalFee'])
            && isset($data['Warehouse'])
            && isset($data['Status'])
            && isset($data['ShopID'])
            && isset($data['Time']);
    }

    /**
     * This function is used to change order status.
     * Handle special case when order status is changed.
     *
     * @param OrderInterface $order
     * @param string $statusCode
     * @return void
     */
    public function handleOrderStatus(OrderInterface $order, string $statusCode): void
    {
        try {
            // Lưu raw GHN status code để dùng cho offline check
            $order->setData('ghn_status', $statusCode);

            if (array_key_exists($statusCode, self::MAPPING_STATE_GHN_MAGENTO)) {
                $order->setState(self::MAPPING_STATE_GHN_MAGENTO[$statusCode]);
            }
            if (array_key_exists($statusCode, self::MAPPING_STATUS_GHN_MAGENTO)) {
                $mappedStatus = self::MAPPING_STATUS_GHN_MAGENTO[$statusCode];
                $order->setStatus($mappedStatus);
            }
            $this->orderRepository->save($order);
        } catch (\Exception $e) {
            $this->logger->error("SAVE ORDER STATUS ERROR: " . $e->getMessage());
        }
    }

    /**
     * This function is used to write note for order.
     * Write note when order status is changed to special status.
     *
     * @param OrderInterface $order
     * @param string $statusCode
     * @return void
     */
    private function writeNote(OrderInterface $order, string $statusCode): void
    {
        try {
            if ($order->canComment() && in_array($statusCode, self::STATUS_NEED_TO_COMMENT)) {
                $statusHistory = $this->historyFactory->create();
                $statusHistory->setComment(
                    __('Comment: %1.', OrderStatus::getStatusDescription($statusCode) . "[$statusCode]")
                );
                $statusHistory->setEntityName(\Magento\Sales\Model\Order::ENTITY);
                $statusHistory->setStatus($order->getStatus());
                $statusHistory->setIsCustomerNotified(true)->setIsVisibleOnFront(true);
                $order->addStatusHistory($statusHistory);
                $this->orderRepository->save($order);
            }
        } catch (\Exception $exception) {
            $this->logger->error("WRITE NOTE ERROR: " . $exception->getMessage());
        }
    }
}
