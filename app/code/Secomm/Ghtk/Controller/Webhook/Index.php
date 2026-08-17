<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Controller\Webhook;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Psr\Log\LoggerInterface;
use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\Tracking\WebhookPayloadParser;
use Secomm\ShippingCore\Api\Tracking\CarrierTrackingProcessorInterface;
use Secomm\ShippingCore\Model\Tracking\TrackingUpdate;

/**
 * GHTK webhook receiver (SL-017 / DEC-SL017-001 §4): Receive → Validate →
 * Parse → Dispatch to the shared tracking processor → Respond. Always HTTP
 * 200 with a fast JSON body — GHTK must never retry indefinitely on our
 * account; unresolvable input is logged, not thrown. CSRF-exempt POST
 * (precedent: Secomm_Ahamove\Controller\Webhooks\Index).
 */
class Index extends Action implements CsrfAwareActionInterface, HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private WebhookPayloadParser $parser,
        private CarrierTrackingProcessorInterface $processor,
        private GhtkConfig $config,
        private LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null; // Intentionally exempt — carrier-to-storefront server callback.
    }

    public function validateForCsrf(RequestInterface $request): bool
    {
        return true; // Intentionally exempt — validated via the shared secret instead.
    }

    public function execute()
    {
        try {
            return $this->handle();
        } catch (\Throwable $e) {
            // Uncontrolled exception must never escape the webhook endpoint.
            $this->logger->error('GHTK webhook failed unexpectedly.', ['exception' => $e->getMessage()]);
            return $this->json(['ok' => false, 'error' => 'internal_error']);
        }
    }

    private function handle(): Json
    {
        $rawBody = (string) $this->getRequest()->getContent();

        // Optional shared-secret guard (GHTK has no documented HMAC — the
        // secret is configured in the GHTK dashboard URL, e.g. ...?secret=X).
        $secret = $this->config->getWebhookSecret();
        if ($secret !== '' && !hash_equals($secret, (string) $this->getRequest()->getParam('secret', ''))) {
            $this->logger->warning('GHTK webhook rejected: invalid secret.');
            return $this->json(['ok' => false, 'error' => 'invalid_secret']);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $this->logger->info('GHTK webhook ignored: payload is not valid JSON.');
            return $this->json(['ok' => false, 'error' => 'invalid_payload']);
        }

        $update = $this->parser->parse($rawBody);
        if ($update === null) {
            $this->logger->info('GHTK webhook ignored: missing identifier or status.');
            return $this->json(['ok' => false, 'error' => 'invalid_payload']);
        }

        // Try every identifier candidate until one resolves to a Magento track.
        $resolved = false;
        foreach ($this->parser->identifierCandidates($payload) as $candidate) {
            $candidateUpdate = $candidate === $update->getTrackingNumber()
                ? $update
                : new TrackingUpdate(
                    $update->getCarrierCode(),
                    $candidate,
                    $update->getNormalizedStatus(),
                    $update->getCarrierStatusCode(),
                    $update->getCarrierStatusMessage(),
                    $update->getOccurredAt(),
                    $update->getSource(),
                    $update->getRaw()
                );
            if ($this->processor->process($candidateUpdate)) {
                $resolved = true;
                break;
            }
        }

        if (!$resolved) {
            $this->logger->info(
                'GHTK webhook processed but no Magento track matched.',
                ['tracking_number' => $update->getTrackingNumber()]
            );
            return $this->json(['ok' => true, 'matched' => false]);
        }

        return $this->json(['ok' => true, 'matched' => true]);
    }

    private function json(array $body): Json
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setData($body);
        $result->setHttpResponseCode(200);

        return $result;
    }
}
