<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Shipment;

/**
 * TASK-4ATBC4 (GHN-E2) — normalized lifecycle-action outcome (cancel / return), mirror of the
 * {@see GhnCreateOutcome} precedent scoped to post-create actions.
 *
 * Semantics (contract matrix §9/§10):
 *   SUCCESS            — provider accepted the action (per-order result === true).
 *   BUSINESS_REJECTED  — provider refused for business reasons (invalid current state, already
 *                        cancelled/returned, not eligible). Deterministic — never retried blind.
 *   TECHNICAL_FAILURE  — transport/5xx/malformed; the mutation MAY have landed — reconcile via
 *                        Order Info (E1 fetcher) before any repeated action.
 *   UNKNOWN_RESULT     — response uncertain (timeout after send); reconcile via Order Info.
 *
 * GHN-E2 owns action execution only — Magento business policy (refund/order cancellation/RMA)
 * stays upstream.
 */
final class GhnActionOutcome
{
    public const ACTION_CANCEL = 'cancel';
    public const ACTION_RETURN = 'return';

    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_BUSINESS_REJECTED = 'BUSINESS_REJECTED';
    public const STATUS_TECHNICAL_FAILURE = 'TECHNICAL_FAILURE';
    public const STATUS_UNKNOWN_RESULT = 'UNKNOWN_RESULT';

    /**
     * @param string|null $providerOrderCode the GHN order code the action targeted
     * @param string|null $reasonCode        provider reason/message code when refused (diagnostic)
     */
    private function __construct(
        private readonly string $action,
        private readonly string $status,
        private readonly string $providerOrderCode,
        private readonly ?string $reasonCode = null,
        private readonly ?string $message = null
    ) {
    }

    public static function success(string $action, string $providerOrderCode): self
    {
        return new self($action, self::STATUS_SUCCESS, $providerOrderCode);
    }

    public static function businessRejected(string $action, string $providerOrderCode, string $reasonCode, ?string $message = null): self
    {
        return new self($action, self::STATUS_BUSINESS_REJECTED, $providerOrderCode, $reasonCode, $message);
    }

    public static function technicalFailure(string $action, string $providerOrderCode, string $reasonCode, ?string $message = null): self
    {
        return new self($action, self::STATUS_TECHNICAL_FAILURE, $providerOrderCode, $reasonCode, $message);
    }

    public static function unknownResult(string $action, string $providerOrderCode, ?string $message = null): self
    {
        return new self($action, self::STATUS_UNKNOWN_RESULT, $providerOrderCode, 'UNKNOWN', $message);
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getProviderOrderCode(): string
    {
        return $this->providerOrderCode;
    }

    public function getReasonCode(): ?string
    {
        return $this->reasonCode;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}
