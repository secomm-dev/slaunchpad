<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Cancel;

use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Secomm\Ghtk\Model\GhtkApiClient;
use Secomm\Ghtk\Model\GhtkApiException;
use Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory;

/**
 * TASK-FNVHK5 — the GHTK CANCEL application service (carrier-owned).
 *
 * Official contract (api.ghtk.vn api-cancel-order; SPIKE-A1DGPY §12):
 * `POST /services/shipment/cancel/{TRACKING_ORDER}` — identifier is the GHTK
 * label code, or the `partner_id:{code}` variant. Cancellation is only allowed
 * in provider states 1 / 2 / 12; later states answer `success=false` with the
 * documented message ("Đơn đã lấy hàng, không thể hủy đơn.").
 *
 * ⚠ METHOD DISCREPANCY: the docs Endpoint section specifies POST while the
 * samples use GET — POST (the formal contract) is implemented here;
 * NEEDS_RUNTIME_VERIFICATION via TASK-44F7V7 (method flip = one line).
 *
 * Single automatic attempt (mutation — §13): transport/5xx failures are
 * TECHNICAL_FAILURE without resend; a manual retry is SAFE because a provider
 * that already processed the first cancel answers the benign
 * ALREADY_CANCELLED state.
 *
 * CALLER EXPECTATION (§18/§19/§20): this service ONLY performs the provider
 * cancellation and returns the typed result. It does NOT mutate Magento order /
 * shipment / payment / refund state, and it is intentionally NOT wired into the
 * Magento order-cancel flow — that orchestration belongs to a separate
 * OrderOperations/bridge concern. Tracking follows normally through the
 * webhook/reconciliation pipeline (status -1 → CANCELLED via GhtkStatusMapper;
 * this service never fakes tracking events).
 */
class CancelShipmentService
{
    /** Transport categories representing unusable technical data. */
    private const TECHNICAL_CATEGORIES = [
        CarrierHttpErrorCategory::NETWORK,
        CarrierHttpErrorCategory::SERVER_ERROR,
        CarrierHttpErrorCategory::TIMEOUT,
        CarrierHttpErrorCategory::INVALID_RESPONSE,
    ];

    public function __construct(
        private readonly GhtkApiClient $apiClient,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Cancels the provider shipment identified by $providerIdentifier
     * (GHTK label — e.g. OrderSubmitResult->labelId — or `partner_id:{code}`).
     * Never throws for domain states: every outcome is a typed kind.
     *
     * @throws LocalizedException only on an empty identifier (programming error)
     */
    public function cancel(string $providerIdentifier): GhtkCancelResponse
    {
        $identifier = trim($providerIdentifier);
        if ($identifier === '') {
            throw new LocalizedException(__('A GHTK label or partner order reference is required to cancel a shipment.'));
        }

        try {
            $response = $this->apiClient->cancelShipment($identifier);
        } catch (GhtkApiException $e) {
            $technical = in_array($e->getCategory(), self::TECHNICAL_CATEGORIES, true);
            $kind = $technical ? GhtkCancelResponse::TECHNICAL_FAILURE : GhtkCancelResponse::BUSINESS_REJECTION;
            $this->log($kind, $identifier, $e->getMessage(), null);

            return $technical
                ? GhtkCancelResponse::technicalFailure($identifier, $e->getMessage())
                : GhtkCancelResponse::businessRejection($identifier, $e->getMessage(), null);
        }

        $message = isset($response['message']) && is_string($response['message']) && $response['message'] !== ''
            ? $response['message']
            : null;
        $logId = isset($response['log_id']) && is_scalar($response['log_id']) ? (string) $response['log_id'] : null;

        if (($response['success'] ?? null) === true) {
            $this->log(GhtkCancelResponse::CANCELLED, $identifier, $message, $logId);

            return GhtkCancelResponse::cancelled($identifier, $logId);
        }

        // Official already-cancelled answer — BENIGN: the cancellation intent is
        // already satisfied. Documented message match (GHTK sends no error_code
        // on cancel responses); an unmatched variant stays BUSINESS_REJECTION
        // (fail-closed) until TASK-44F7V7 verifies the runtime wording.
        if (
            $message !== null
            && self::normalize($message) === self::normalize(GhtkCancelResponse::ALREADY_CANCELLED_MESSAGE)
        ) {
            $this->log(GhtkCancelResponse::ALREADY_CANCELLED, $identifier, $message, $logId);

            return GhtkCancelResponse::alreadyCancelled($identifier, $message, $logId);
        }

        $this->log(GhtkCancelResponse::BUSINESS_REJECTION, $identifier, $message, $logId);

        return GhtkCancelResponse::businessRejection($identifier, $message, $logId);
    }

    /**
     * Whitespace/case-insensitive comparison for the documented benign message —
     * never fuzzy, never applied to any other message.
     */
    private static function normalize(string $message): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($message)) ?? $message);
    }

    /**
     * Masked diagnostics only (§30): identifier + classification + provider
     * message/log_id — no token, no customer address, no telephone, no raw payload.
     */
    private function log(string $kind, string $identifier, ?string $message, ?string $logId): void
    {
        $level = $kind === GhtkCancelResponse::TECHNICAL_FAILURE ? 'warning' : 'info';
        $this->logger->{$level}(
            'GHTK cancel outcome: ' . $kind . '.',
            [
                'identifier' => $identifier,
                'message' => $message !== null ? mb_substr($message, 0, 200) : null,
                'log_id' => $logId,
            ]
        );
    }
}
