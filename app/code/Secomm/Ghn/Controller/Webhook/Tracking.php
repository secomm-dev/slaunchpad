<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Controller\Webhook;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\Tracking\WebhookPayloadParser;
use Secomm\ShippingCore\Api\Tracking\CarrierTrackingProcessorInterface;

/**
 * TASK-GKHXY1 (GHN-E1) — GHN webhook receiver: Receive → Validate secret → Parse → Dispatch to
 * the shared tracking processor → Respond. Lifecycle translation ONLY — the Magento order
 * business state is NEVER mutated here (orchestration belongs upstream).
 *
 * Route: POST /secomm_ghn/webhook/tracking (frontName `secomm_ghn` — legacy `ghn` frontName is
 * taken by Secomm_GiaoHangNhanh; register the full URL in the GHN dashboard).
 *
 * Security model (contract matrix §11/D8, wording per TL r2): GHN does NOT provide a
 * provider-signed webhook/HMAC signature; the GHN Developer Portal supports merchant-configured
 * custom headers for webhook callbacks. Secomm uses header `X-Secomm-Ghn-Secret` as a
 * merchant-configured shared secret; Magento validates it with a constant-time comparison
 * (`hash_equals`) and fails closed (401 on missing/invalid secret, 0 processing). This is
 * shared-secret authentication — NOT cryptographic provider signature verification.
 *
 * Response: JSON. 200 = received (ok:true/matched, or ok:false for permanently-unprocessable
 * payloads — GHN drops non-408/429 4xx, so malformed input is acked-drop, never retried);
 * 401 = secret mismatch; 500 = internal error (GHN retry backoff 30s→12h applies).
 */
class Tracking extends Action implements CsrfAwareActionInterface, HttpPostActionInterface
{
    /** Merchant-defined callback authentication header (configured in the GHN dashboard). */
    public const SECRET_HEADER = 'X-Secomm-Ghn-Secret';

    public function __construct(
        Context $context,
        private readonly WebhookPayloadParser $parser,
        private readonly CarrierTrackingProcessorInterface $processor,
        private readonly Config $config,
        private readonly GhnLogger $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null; // Intentionally exempt — carrier server callback, authenticated by secret.
    }

    public function validateForCsrf(RequestInterface $request): bool
    {
        return true; // Intentionally exempt — validated via the shared secret instead.
    }

    public function execute()
    {
        try {
            return $this->handle();
        } catch (\Throwable $exception) {
            // Uncontrolled exception must never escape the webhook endpoint; 500 lets GHN retry.
            $this->logger->error('GHN webhook failed unexpectedly.', ['exception' => $exception->getMessage()]);

            return $this->json(['ok' => false, 'error' => 'internal_error'], 500);
        }
    }

    private function handle(): Json
    {
        $secret = $this->config->getWebhookSecret();
        $provided = (string) $this->getRequest()->getHeader(self::SECRET_HEADER);
        if ($secret === '' || $provided === '' || !hash_equals($secret, $provided)) {
            // Fail-closed (matrix D8 anti-defect): GHN has no signature — an unauthenticated
            // callback must never touch tracking state. 401 ⇒ GHN drops the delivery.
            $this->logger->warning('GHN webhook rejected: invalid or missing secret header.', ['reason' => 'invalid_secret']);

            return $this->json(['ok' => false, 'error' => 'invalid_secret'], 401);
        }

        $payload = json_decode((string) $this->getRequest()->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['ok' => false, 'error' => 'invalid_payload']);
        }

        // Structurally valid payload carrying a documented non-lifecycle event type (create /
        // update_weight / update_cod / update_fee / update_payment_type / cod /
        // update_partial_return, or an unrecognized future type) → explicit ack with
        // unsupported_event_type (never invalid_payload — the payload itself is well-formed;
        // GHN-E2 may consume fee/COD events later). The parser never sees it.
        $type = (string) ($payload['Type'] ?? '');
        if ($type !== '' && $type !== WebhookPayloadParser::TYPE_SWITCH_STATUS) {
            return $this->json(['ok' => true, 'matched' => false, 'error' => 'unsupported_event_type']);
        }

        $update = $this->parser->parse($payload);
        if ($update === null) {
            return $this->json(['ok' => false, 'error' => 'invalid_payload']);
        }

        $matched = $this->processor->process($update);

        return $this->json(['ok' => true, 'matched' => $matched]);
    }

    private function json(array $data, int $httpCode = 200): Json
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setHttpResponseCode($httpCode);
        $result->setData($data);

        return $result;
    }
}
