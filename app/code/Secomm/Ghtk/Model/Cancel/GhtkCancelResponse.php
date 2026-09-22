<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Cancel;

/**
 * TASK-FNVHK5 — typed GHTK CANCEL result (carrier-owned; the RATE
 * `CarrierRateOutcome` semantics deliberately do NOT apply to CANCEL).
 *
 * Four kinds mirror the cancellation lifecycle:
 *
 * - CANCELLED           provider accepted the cancellation;
 * - ALREADY_CANCELLED   provider reports the shipment was already cancelled — a
 *                       BENIGN/idempotent end-state: the cancellation INTENT is
 *                       satisfied (official response is success=false + the
 *                       documented already-cancelled message — GHTK provides no
 *                       error_code field on cancel responses, so the documented
 *                       message IS the contract; exact string is
 *                       NEEDS_RUNTIME_VERIFICATION via TASK-44F7V7);
 * - BUSINESS_REJECTION  cannot cancel in the current provider state ("Đơn đã
 *                       lấy hàng…"), unknown shipment, auth/config (403),
 *                       invalid request (400) — never retried;
 * - TECHNICAL_FAILURE   timeout / network / HTTP 5xx / unusable response —
 *                       never auto-retried (a mutation may already have landed;
 *                       a manual retry safely resolves via ALREADY_CANCELLED).
 *
 * No Magento order/payment/refund state is mutated here — the caller owns the
 * lifecycle decision.
 */
final class GhtkCancelResponse
{
    public const CANCELLED = 'CANCELLED';
    public const ALREADY_CANCELLED = 'ALREADY_CANCELLED';
    public const BUSINESS_REJECTION = 'BUSINESS_REJECTION';
    public const TECHNICAL_FAILURE = 'TECHNICAL_FAILURE';

    /** Official already-cancelled answer (api.ghtk.vn api-cancel-order). */
    public const ALREADY_CANCELLED_MESSAGE = 'Đơn hàng đã đã ở trạng thái hủy';

    private function __construct(
        private readonly string $kind,
        private readonly string $identifier,
        private readonly ?string $message = null,
        private readonly ?string $logId = null
    ) {
    }

    public static function cancelled(string $identifier, ?string $logId = null): self
    {
        return new self(self::CANCELLED, $identifier, null, $logId);
    }

    public static function alreadyCancelled(string $identifier, ?string $message, ?string $logId): self
    {
        return new self(self::ALREADY_CANCELLED, $identifier, $message, $logId);
    }

    public static function businessRejection(string $identifier, ?string $message, ?string $logId): self
    {
        return new self(self::BUSINESS_REJECTION, $identifier, $message, $logId);
    }

    public static function technicalFailure(string $identifier, ?string $message): self
    {
        return new self(self::TECHNICAL_FAILURE, $identifier, $message);
    }

    /** CANCELLED | ALREADY_CANCELLED | BUSINESS_REJECTION | TECHNICAL_FAILURE */
    public function getKind(): string
    {
        return $this->kind;
    }

    /** The provider identifier this cancellation targeted. */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /** Provider/diagnostic message (truncated by the service) — never control flow. */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /** GHTK tracing id (log_id) for support requests, when present. */
    public function getLogId(): ?string
    {
        return $this->logId;
    }

    /** Cancellation intent satisfied (successful OR benign already-cancelled). */
    public function isSatisfied(): bool
    {
        return $this->kind === self::CANCELLED || $this->kind === self::ALREADY_CANCELLED;
    }
}
