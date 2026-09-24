<?php
/**
 * MoMo Notify (IPN) controller — payment-first (MOMO-01).
 *
 * MoMo POSTs the authoritative payment result here. The controller is THIN:
 * parse the payload, delegate to IpnProcessor (outcomes only), serialize the
 * MoMo HTTP/JSON contract. NO order lookup, NO signature checks, NO state
 * mutations in the controller.
 *
 * HTTP mapping (spec §4.2):
 *  - SUCCESS / ACK_RECONCILIATION -> 200 {"resultCode": 0} (stop retry);
 *  - INVALID_CALLBACK             -> 200 resultCode 1 (stop retry);
 *  - UNKNOWN_REFERENCE            -> 404 resultCode 1;
 *  - RETRYABLE_FAILURE            -> 500 resultCode 1 — MoMo's retry
 *    policy is the AC9 money-real-but-not-finalized recovery driver (no
 *    local recovery cron in scope).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Controller\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Service\IpnProcessor;

/**
 * MoMo Notify (IPN) controller — thin delegate, composition style.
 */
class Notify implements CsrfAwareActionInterface, HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * Notify controller constructor.
     *
     * @param Http $request
     * @param JsonFactory $resultJsonFactory
     * @param SerializerJson $serializer
     * @param IpnProcessor $ipnProcessor
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Http $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly SerializerJson $serializer,
        private readonly IpnProcessor $ipnProcessor,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Handle the MoMo IPN POST/GET.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();

        $rawBody = (string)$this->request->getContent();
        $payload = $rawBody !== ''
            ? (array)$this->serializer->unserialize($rawBody)
            : $this->request->getParams();

        try {
            $outcome = $this->ipnProcessor->process($payload);
        } catch (\Exception $e) {
            // Defensive: the processor returns outcomes and should not throw,
            // but a crash here must not leak internals and must be retryable
            // (MoMo retries on 5xx).
            $this->logger->critical('MoMo IPN processing crashed: ' . $e->getMessage());
            $resultJson->setHttpResponseCode(500);

            return $resultJson->setData(['resultCode' => 1, 'message' => 'Temporary failure']);
        }

        switch ($outcome) {
            case IpnProcessor::OUTCOME_SUCCESS:
            case IpnProcessor::OUTCOME_ACK_RECONCILIATION:
                return $resultJson->setData(['resultCode' => 0]);
            case IpnProcessor::OUTCOME_UNKNOWN_REFERENCE:
                $resultJson->setHttpResponseCode(404);

                return $resultJson->setData(['resultCode' => 1, 'message' => 'Reference not found']);
            case IpnProcessor::OUTCOME_INVALID_CALLBACK:
                return $resultJson->setData(['resultCode' => 1, 'message' => 'Invalid callback']);
            case IpnProcessor::OUTCOME_RETRYABLE_FAILURE:
            default:
                $resultJson->setHttpResponseCode(500);

                return $resultJson->setData(['resultCode' => 1, 'message' => 'Temporary failure']);
        }
    }

    /**
     * Create exception in case CSRF validation failed.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Perform custom request validation.
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
