<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Controller\Payment;

use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Service\IpnProcessor;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;

/**
 * ZaloPay IPN (server-to-server callback) controller — payment-first only.
 *
 * The controller is composition-only (no business logic): it parses the
 * payload, delegates to IpnProcessor and serializes the EXACT official
 * ZaloPay callback response contract
 * (https://docs.zalopay.vn/docs/specs/callback-api/ + knowledge base
 * "Callback"): HTTP 200 JSON `{return_code, return_message}` —
 *  - return_code 1 ("Success"): processed / valid evidence recorded;
 *  - return_code 2 ("Invalid"): MAC failure, malformed or unknown reference;
 *  - return_code 0 (official sample: "callback again (up to 3 times)"):
 *    transient failure or still-processing payment.
 *
 * The legacy `{errors, messages}` body with 404/500 codes was NOT the
 * provider protocol (corrective round 3, Blocker 3).
 *
 * POST-only (corrective round 3): the official callback contract is
 * `Method: POST` — HttpGetActionInterface was removed; the runtime guard
 * below is defence in depth.
 */
class Ipn implements CsrfAwareActionInterface, HttpPostActionInterface
{
    /**
     * Official response bodies per IpnProcessor domain outcome.
     *
     * @var array<string, array{0: int, 1: string}>
     */
    private const RESPONSE_BY_OUTCOME = [
        IpnProcessor::OUTCOME_SUCCESS => [1, 'Success'],
        IpnProcessor::OUTCOME_ACK_RECONCILIATION => [1, 'Success'],
        IpnProcessor::OUTCOME_INVALID_CALLBACK => [2, 'Invalid'],
        IpnProcessor::OUTCOME_RETRYABLE_FAILURE => [0, 'Temporary failure, please retry.'],
    ];

    /**
     * Ipn constructor.
     *
     * @param Http $request
     * @param JsonFactory $resultJsonFactory
     * @param SerializerJson $serializer
     * @param Logger $logger
     * @param IpnProcessor $ipnProcessor
     */
    public function __construct(
        private readonly Http            $request,
        private readonly JsonFactory     $resultJsonFactory,
        private readonly SerializerJson  $serializer,
        private readonly Logger          $logger,
        private readonly IpnProcessor    $ipnProcessor
    ) {
    }

    /**
     * Handle the ZaloPay server-to-server IPN callback.
     *
     * @return Json|null Null for non-POST probes (the provider contract is POST-only).
     */
    public function execute(): ?Json
    {
        if (!$this->request->isPost()) {
            return null;
        }
        $rawContent = (string)$this->request->getContent();
        $this->logger->info(
            'ZaloPay IPN Hit. Content: ' . $rawContent
            . ' Params: ' . json_encode($this->request->getParams())
        );
        $resultJson = $this->resultJsonFactory->create();
        try {
            $response = [];
            if ($rawContent !== '') {
                try {
                    $response = $this->serializer->unserialize($rawContent);
                } catch (\Exception $e) {
                    $response = $this->request->getParams();
                }
            } else {
                $response = $this->request->getParams();
            }

            if (isset($response['data']) && is_string($response['data'])) {
                $response['trans_data'] = $this->serializer->unserialize($response['data']);
            }

            $this->logger->info('ZaloPay IPN Parsed Response: ' . json_encode($response));

            $outcome = $this->ipnProcessor->process($response);
            [$returnCode, $returnMessage] = self::RESPONSE_BY_OUTCOME[$outcome]
                ?? [0, 'Temporary failure, please retry.'];

            // ALWAYS HTTP 200: the provider protocol lives in the JSON body
            // (return_code) — the official sample answers res.json(result)
            // for every branch.
            return $resultJson->setData(
                [
                    'return_code' => $returnCode,
                    'return_message' => $returnMessage,
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error('ZaloPay IPN Exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            // Transient: the official sample's return_code 0 = callback again.
            return $resultJson->setData(
                [
                    'return_code' => 0,
                    'return_message' => 'Temporary failure, please retry.',
                ]
            );
        }
    }

    /**
     * Create exception in case CSRF validation failed.
     *
     * Return null if default exception will suffice.
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
     * Return null if default validation is needed.
     *
     * @param RequestInterface $request
     * @return boolean|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
