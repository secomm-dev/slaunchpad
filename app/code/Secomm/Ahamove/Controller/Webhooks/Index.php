<?php declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2023. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Controller\Webhooks;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Encryption\Helper\Security;
use Secomm\Ahamove\Logger\Logger;
use Secomm\Ahamove\Helper\Data as AhamoveHelperData;
use Secomm\Ahamove\Model\AhamoveOrderStatusFactory;
use Secomm\Ahamove\Model\ResourceModel\AhamoveOrderStatus as AhamoveOrderStatusResource;
use Secomm\Ahamove\Model\Tracking\WebhookTrackingBridge;

class Index implements CsrfAwareActionInterface, HttpPostActionInterface
{
    /**
     * @param Logger $logger
     * @param AhamoveOrderStatusResource $ahamoveOrderStatusResource
     * @param AhamoveOrderStatusFactory $ahamoveOrderStatusFactory
     * @param AhamoveHelperData $helperData
     * @param ScopeConfigInterface $scopeConfig
     * @param JsonFactory $jsonResultFactory
     * @param RequestInterface $request
     * @param WebhookTrackingBridge $trackingBridge
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly AhamoveOrderStatusResource $ahamoveOrderStatusResource,
        private readonly AhamoveOrderStatusFactory $ahamoveOrderStatusFactory,
        private readonly AhamoveHelperData $helperData,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly JsonFactory $jsonResultFactory,
        private readonly RequestInterface $request,
        private readonly WebhookTrackingBridge $trackingBridge
    ) {
    }

    /**
     * Validate webhook request source by comparing api_key in the payload
     * against the configured Ahamove API key.
     *
     * Ahamove authenticates webhook callbacks by including the partner's
     * api_key field in the JSON payload body — it does NOT use HMAC signatures.
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
     * @return Json
     */
    public function execute(): Json
    {
        $rawBody = $this->request->getContent();

        if (empty($rawBody)) {
            return $this->jsonResultFactory->create()->setData(['error' => true, 'message' => 'Empty request']);
        }

        if (!$this->validateWebhookSignature($rawBody)) {
            $this->logger->warning('Webhook authentication failed');
            return $this->jsonResultFactory->create()
                ->setHttpResponseCode(401)
                ->setData(['error' => true, 'message' => 'Unauthorized']);
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            return $this->jsonResultFactory->create()->setData(['error' => true, 'message' => 'Invalid JSON']);
        }

        try {
            if (empty($data['_id'])) {
                return $this->jsonResultFactory->create()->setData(['error' => true, 'message' => 'Missing _id']);
            }

            $status = $data['status'] ?? '';
            $statusLabel = '';
            if ($status === 'COMPLETED') {
                if (isset($data['path'][1]['status']) && isset($data['path'][1]['fail_comment'])) {
                    $status = $data['path'][1]['status'];
                    $statusLabel = $data['path'][1]['fail_comment'];
                } elseif (isset($data['path'][0]['status']) && isset($data['path'][0]['fail_comment'])) {
                    $status = $data['path'][0]['status'];
                    $statusLabel = $data['path'][0]['fail_comment'];
                }
            }
            if (empty($statusLabel)) {
                $statusLabel = $this->helperData->getStatusLabel($status);
            }

            // Resolve tracking code: try order.tracking_code, parse shared_link (e.g. /s/260818VNWB7M), or path[1].tracking_number
            $trackNumber = $data['order']['tracking_code'] ?? '';
            if (empty($trackNumber) && !empty($data['shared_link'])) {
                $pathParts = explode('/', parse_url($data['shared_link'], PHP_URL_PATH) ?: '');
                $trackNumber = end($pathParts);
            }
            if (empty($trackNumber) && !empty($data['path'][1]['tracking_number'])) {
                $trackNumber = $data['path'][1]['tracking_number'];
            }

            // Save raw Ahamove status record (audit / admin tracking display)
            $ahamoveOrderData = [
                'order_ahamove_id' => $data['_id'] ?? '',
                'track_number'     => $trackNumber,
                'status'           => $status,
                'shared_link'      => $data['shared_link'] ?? '',
                'order_data'       => json_encode($data),
            ];
            $ahamoveOrderStatus = $this->ahamoveOrderStatusFactory->create();
            $ahamoveOrderStatus->setData($ahamoveOrderData);
            $this->ahamoveOrderStatusResource->save($ahamoveOrderStatus);
            // Notify seller on failure/cancellation statuses
            if (in_array($status, ['CANCELLED', 'RETURNED', 'IN_RETURN', 'FAILED'], true)) {
                $incrementId = $data['external_id'] ?? $data['supplier_id'] ?? 'N/A';
                $content = "Ahamove order id='{$data['_id']}' failed, external id='{$incrementId}'";
                $this->helperData->sendNotifyWebhookAhamove($content);
            }

            // Feed Secomm_ShippingCore tracking pipeline (deduplication + out-of-order protection)
            if (!empty($trackNumber)) {
                try {
                    $this->trackingBridge->process($data, (string)$trackNumber);
                } catch (\Throwable $e) {
                    $this->logger->error('Failed to process Ahamove tracking via ShippingCore: ' . $e->getMessage());
                }
            } else {
                $this->logger->warning('[Webhook] Cannot forward to WebhookTrackingBridge because tracking_code is empty in payload');
            }
        } catch (\Throwable $e) {
            $this->logger->error('Ahamove Webhook error: ' . $e->getMessage());
            return $this->jsonResultFactory->create()->setData(['error' => true, 'message' => 'Internal error']);
        }

        return $this->jsonResultFactory->create()->setData(['success' => true]);
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
}
