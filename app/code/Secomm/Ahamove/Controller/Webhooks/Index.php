<?php
/**
 * @author Secomm SCS Team
 * @copyright Copyright (c) 2023. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\Ahamove\Controller\Webhooks;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NotFoundException;
use Secomm\Ahamove\Logger\Logger;

class Index extends Action implements CsrfAwareActionInterface, HttpPostActionInterface
{

    /**
     * @var Logger
     */
    protected $logger;
    protected $packageFactory;
    protected $packageResource;
    protected $moduleDir;
    protected $ahamoveOrderStatusResource;
    protected $ahamoveOrderStatusFactory;
    protected $orderRepository;
    protected $transactionFactory;
    protected $orderConverter;
    protected $shipmentExtensionFactory;
    protected $helperData;

    public function __construct(
        Logger                     $logger,
        Context                    $context,
        \Secomm\PackagingManager\Model\PackageFactory $packageFactory,
        \Secomm\PackagingManager\Model\ResourceModel\Package $packageResource,
        \Magento\Framework\Module\Dir\Reader $moduleDir,
        \Secomm\Ahamove\Model\ResourceModel\AhamoveOrderStatus $ahamoveOrderStatusResource,
        \Secomm\Ahamove\Model\AhamoveOrderStatusFactory $ahamoveOrderStatusFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Convert\OrderFactory $convertOrderFactory,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Api\Data\ShipmentExtensionFactory $shipmentExtensionFactory,
        \Secomm\Ahamove\Helper\Data $helperData
    ) {
        $this->logger = $logger;
        $this->packageFactory = $packageFactory;
        $this->packageResource = $packageResource;
        $this->moduleDir = $moduleDir;
        $this->ahamoveOrderStatusResource = $ahamoveOrderStatusResource;
        $this->ahamoveOrderStatusFactory = $ahamoveOrderStatusFactory;
        $this->orderRepository = $orderRepository;
        $this->orderConverter = $convertOrderFactory->create();
        $this->transactionFactory = $transactionFactory;
        $this->shipmentExtensionFactory = $shipmentExtensionFactory;
        $this->helperData = $helperData;
        parent::__construct($context);
    }

    /**
     * Execute action based on request and return result
     *
     * @return void
     * @throws NotFoundException
     */
    public function execute()
    {
        $data = $this->getRequest()->getContent();
        $data = json_decode($data, true);

        try {
            $package = $this->packageFactory->create();
            if (isset($data['_id']) && !empty($data['_id'])) {
                $this->packageResource->load($package, $data['_id'], 'track_number');
                if (!$package->getId()) {
                    $this->packageResource->load($package, $data['_id'], 'service_order_id');
                }
                // Case double and fails id
                if ($data['status'] == 'IN PROCESS' && is_null($package->getId())) {
                    return;
                }

                $status = $data['status'] ?? '';
                $statusLabel = '';
                if ($status == 'COMPLETED') {
                    if (isset($data['path'][1]['status']) && isset($data['path'][1]['fail_comment'])) {
                        $status = $data['path'][1]['status'];
                        $statusLabel = $data['path'][1]['fail_comment'];
                    } elseif (isset($data['path'][0]['status']) && isset($data['path'][1]['fail_comment'])) {
                        $status = $data['path'][1]['status'];
                        $statusLabel = $data['path'][1]['fail_comment'];
                    }
                }

                $additionalData = [];
                if (isset($data['cancel_comment']) && $status == 'CANCELLED') {
                    $additionalData = [
                      'Cancel Comment' => $data['cancel_comment'] ?? ''
                    ];
                }
                if (!empty($additionalData)) {
                    $package->setAdditionalData(json_encode($additionalData));
                }
                
                if (empty($statusLabel)) {
                    $statusLabel = $this->getStatusLabel($status);
                }
                $package->setStatus($status);
                $package->setStatusLabel($statusLabel);
                $this->packageResource->save($package);

                $ahamoveOrderData = [
                    'order_ahamove_id' => $data['_id'] ?? '',
                    'track_number' => $package->getTrackNumber() ?? '',
                    'status' => $data['status'] ?? '',
                    'shared_link' => $data['shared_link'] ?? '',
                    'order_data' => json_encode($data)
                ];

                // Save Status Shipping from Ahamove
                $ahamoveOrderStatus = $this->ahamoveOrderStatusFactory->create();
                $ahamoveOrderStatus->setData($ahamoveOrderData);
                $this->ahamoveOrderStatusResource->save($ahamoveOrderStatus);

                // send notify to seller when shipment on ahamove failed
                if (in_array($status, ['CANCELLED', 'RETURNED', 'IN_RETURN', 'FAILED'])) {
                    $incrementId = $package->getOrder()->getIncrementId();
                    $content = "Package id='{$package->getIncrementId()}' on ahamove failed, order id='{$incrementId}'";
                    $this->helperData->sendNotifyWebhookAhamove($content);
                }

                // Create Shipment Based on package Object
                return $package;
            }
        } catch (\Exception $e) {
            $this->logger->error('Webhook : ' . $e->getMessage());
        }
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
     * Get status label of ahamove service
     * @param string $statusCode
     * @return string
     * @throws \Exception
     */
    public function getStatusLabel($statusCode=null)
    {
        $statusLabel = '';
        try {
            $filePath = $this->moduleDir->getModuleDir('', 'Secomm_Ahamove') . '/File/shipping_status.json';
            $jsonData = file_get_contents($filePath);
            $dataShippingStatus = json_decode($jsonData, true);

            if (count($dataShippingStatus) == 0) {
                return $statusLabel;
            }

            foreach ($dataShippingStatus as $data) {
                if (strtoupper($data['status_code']) == strtoupper($statusCode)) {
                    $statusLabel = $data['title'];
                    break;
                }
            }

            return $statusLabel;
        } catch (\Exception $e) {
        }

        return $statusLabel;
    }
}
