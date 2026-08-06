<?php
/**
 * MoMo Notify (IPN) controller.
 *
 * MoMo POSTs the authoritative payment result here. We verify the signature,
 * finalize the order, and reply HTTP 200 so MoMo stops retrying.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class Notify extends Action implements CsrfAwareActionInterface, HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * Constructor
     *
     * @param Context $context
     * @param CommandPoolInterface $commandPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SerializerJson $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        private readonly CommandPoolInterface $commandPool,
        private readonly PaymentDataObjectFactory $paymentDataObjectFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SerializerJson $serializer,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Handle the MoMo IPN POST.
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        /** @var Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        $rawBody = $this->getRequest()->getContent();
        $response = $rawBody ? (array)$this->serializer->unserialize($rawBody) : $this->getRequest()->getParams();

        try {
            $incrementId = (string)($response['orderId'] ?? '');
            $order = $this->loadOrderByIncrementId($incrementId);

            if ($order === null) {
                // Unknown order — do not acknowledge as success (could be spam/probe).
                $resultJson->setHttpResponseCode(404);

                return $resultJson->setData(['resultCode' => 1, 'message' => 'Order not found']);
            }

            if ($order->getState() !== Order::STATE_PENDING_PAYMENT) {
                // Already processed (idempotent) — acknowledge so MoMo stops retrying.
                return $resultJson->setData(['resultCode' => 0]);
            }

            $payment = $order->getPayment();
            ContextHelper::assertOrderPayment($payment);
            $paymentDataObject = $this->paymentDataObjectFactory->create($payment);

            $this->commandPool->get('notify')->execute([
                'payment' => $paymentDataObject,
                'response' => $response,
            ]);

            return $resultJson->setData(['resultCode' => 0]);
        } catch (\Exception $e) {
            $this->logger->error('MoMo notify failed: ' . $e->getMessage(), ['exception' => get_class($e)]);

            // Non-2xx makes MoMo retry the IPN.
            $resultJson->setHttpResponseCode(500);

            return $resultJson->setData(['resultCode' => 1, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Load an order by increment id via the repository (no deprecated ->load()).
     *
     * @param string $incrementId
     * @return \Magento\Sales\Api\Data\OrderInterface|null
     */
    private function loadOrderByIncrementId(string $incrementId)
    {
        if ($incrementId === '') {
            return null;
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId)
            ->create();
        $orders = $this->orderRepository->getList($searchCriteria)->getItems();

        return $orders ? reset($orders) : null;
    }

    /**
     * @inheritdoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
