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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Encryption\Helper\Security;
use Secomm\Ahamove\Logger\Logger;

class Index extends Action implements CsrfAwareActionInterface, HttpPostActionInterface
{

    /**
     * @var Logger
     */
    protected $logger;
    protected $moduleDir;
    protected $ahamoveOrderStatusResource;
    protected $ahamoveOrderStatusFactory;
    protected $orderRepository;
    protected $transactionFactory;
    protected $orderConverter;
    protected $shipmentExtensionFactory;
    protected $helperData;
    protected $scopeConfig;

    public function __construct(
        Logger                     $logger,
        Context                    $context,
        \Magento\Framework\Module\Dir\Reader $moduleDir,
        \Secomm\Ahamove\Model\ResourceModel\AhamoveOrderStatus $ahamoveOrderStatusResource,
        \Secomm\Ahamove\Model\AhamoveOrderStatusFactory $ahamoveOrderStatusFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Convert\OrderFactory $convertOrderFactory,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Api\Data\ShipmentExtensionFactory $shipmentExtensionFactory,
        \Secomm\Ahamove\Helper\Data $helperData,
        ScopeConfigInterface       $scopeConfig
    ) {
        $this->logger = $logger;
        $this->moduleDir = $moduleDir;
        $this->ahamoveOrderStatusResource = $ahamoveOrderStatusResource;
        $this->ahamoveOrderStatusFactory = $ahamoveOrderStatusFactory;
        $this->orderRepository = $orderRepository;
        $this->orderConverter = $convertOrderFactory->create();
        $this->transactionFactory = $transactionFactory;
        $this->shipmentExtensionFactory = $shipmentExtensionFactory;
        $this->helperData = $helperData;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    /**
     * Validate webhook request source by comparing api_key in the payload
     * against the configured Ahamove API key.
     *
     * Ahamove authenticates webhook callbacks by including the partner's
     * api_key field in the JSON payload body — it does NOT use HMAC signatures.
     *
     * @param string $rawBody
     * @return bool
     */
    private function validateWebhookSignature(string $rawBody): bool
    {
        $data = json_decode($rawBody, true);

        if (!isset($data['api_key']) || empty($data['api_key'])) {
            $this->logger->warning('Webhook payload missing api_key field');
            return false;
        }

        $mode = (string)$this->scopeConfig->getValue(
            'ahamove/general/mode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $configPath = $mode === 'production'
            ? 'ahamove/general/production_api_key'
            : 'ahamove/general/staging_api_key';

        $expectedApiKey = (string)$this->scopeConfig->getValue(
            $configPath,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if (empty($expectedApiKey)) {
            $this->logger->warning('Webhook API key not configured (' . $configPath . ' is empty)');
            return false;
        }

        $match = Security::compareStrings($expectedApiKey, $data['api_key']);

        if (!$match) {
            $this->logger->warning('Webhook api_key mismatch');
        }

        return $match;
    }

    /**
     * Execute action based on request and return result
     *
     * @return void
     * @throws NotFoundException
     */
    public function execute()
    {
        $rawBody = $this->getRequest()->getContent();

        if (!$this->validateWebhookSignature($rawBody)) {
            $this->logger->warning('Webhook authentication failed');
            $this->getResponse()->setHttpResponseCode(401);
            $this->getResponse()->setBody(json_encode(['error' => 'Unauthorized']));
            return;
        }

        $data = json_decode($rawBody, true);

        try {
            if (isset($data['_id']) && !empty($data['_id'])) {
                $status = $data['status'] ?? '';
                $statusLabel = '';
                if ($status == 'COMPLETED') {
                    if (isset($data['path'][1]['status']) && isset($data['path'][1]['fail_comment'])) {
                        $status = $data['path'][1]['status'];
                        $statusLabel = $data['path'][1]['fail_comment'];
                    } elseif (isset($data['path'][0]['status']) && isset($data['path'][0]['fail_comment'])) {
                        $status = $data['path'][0]['status'];
                        $statusLabel = $data['path'][0]['fail_comment'];
                    }
                }

                if (empty($statusLabel)) {
                    $statusLabel = $this->getStatusLabel($status);
                }

                $ahamoveOrderData = [
                    'order_ahamove_id' => $data['_id'] ?? '',
                    'track_number' => $data['order']['tracking_code'] ?? '',
                    'status' => $status,
                    'shared_link' => $data['shared_link'] ?? '',
                    'order_data' => json_encode($data)
                ];

                // Save Status Shipping from Ahamove
                $ahamoveOrderStatus = $this->ahamoveOrderStatusFactory->create();
                $ahamoveOrderStatus->setData($ahamoveOrderData);
                $this->ahamoveOrderStatusResource->save($ahamoveOrderStatus);

                // send notify to seller when shipment on ahamove failed
                if (in_array($status, ['CANCELLED', 'RETURNED', 'IN_RETURN', 'FAILED'])) {
                    $incrementId = $data['external_id'] ?? $data['supplier_id'] ?? 'N/A';
                    $content = "Ahamove order id='{$data['_id']}' failed, external id='{$incrementId}'";
                    $this->helperData->sendNotifyWebhookAhamove($content);
                }

                return $ahamoveOrderStatus;
            }
        } catch (\Exception $e) {
            $this->logger->error('Webhook : ' . $e->getMessage());
        }

        return null;
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
     * Get status label of ahamove service — delegates to Helper
     * @param string $statusCode
     * @return string
     */
    public function getStatusLabel($statusCode = null)
    {
        return $this->helperData->getStatusLabel($statusCode);
    }
}
