<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\OrderSubmit;

/**
 * TASK-BE5YD2 — typed GHTK CREATE response result (carrier-owned; the RATE
 * `CarrierRateOutcome` semantics deliberately do NOT apply to CREATE).
 *
 * Four kinds mirror the create lifecycle:
 *
 * - CREATED              success=true + usable provider shipment identity
 *                        (label and/or tracking) — normal creation;
 * - DUPLICATE_EXISTING   success=false + error_code=ORDER_ID_EXIST + provider
 *                        identity fields (partner_id / ghtk_label / status) —
 *                        a RECOVERY CANDIDATE: the SERVICE validates the
 *                        partner_id against the submitted deterministic order.id
 *                        before treating it as recovered (never auto-accepted);
 * - BUSINESS_REJECTION   success=false + any other error_code/message —
 *                        deterministic business/config failure, no retry;
 * - MALFORMED            missing/garbage success contract, or success=true
 *                        without usable shipment identity — technical failure,
 *                        no Magento shipment may be built from it.
 *
 * Identity normalization (§19): normal success (partner_id/label/tracking_id)
 * and duplicate (partner_id/ghtk_label/status) both yield the same fields here
 * so the service treats them uniformly. Duplicate field positions (top-level vs
 * order block) are parsed defensively — exact shape is
 * NEEDS_RUNTIME_VERIFICATION (TASK-44F7V7); a wrong/missing identity hard-fails
 * downstream instead of silently recovering.
 */
final class GhtkCreateResponse
{
    public const KIND_CREATED = 'CREATED';
    public const KIND_DUPLICATE_EXISTING = 'DUPLICATE_EXISTING';
    public const KIND_BUSINESS_REJECTION = 'BUSINESS_REJECTION';
    public const KIND_MALFORMED = 'MALFORMED';

    public const ERROR_ORDER_ID_EXIST = 'ORDER_ID_EXIST';

    private function __construct(
        private readonly string $kind,
        private readonly ?string $partnerId = null,
        private readonly ?string $label = null,
        private readonly ?string $trackingNumber = null,
        private readonly ?string $providerStatus = null,
        private readonly ?string $errorCode = null,
        private readonly ?string $message = null
    ) {
    }

    public static function created(
        ?string $partnerId,
        ?string $label,
        ?string $trackingNumber,
        ?string $providerStatus
    ): self {
        return new self(self::KIND_CREATED, $partnerId, $label, $trackingNumber, $providerStatus);
    }

    public static function duplicateExisting(
        ?string $partnerId,
        ?string $label,
        ?string $trackingNumber,
        ?string $providerStatus,
        ?string $message
    ): self {
        return new self(
            self::KIND_DUPLICATE_EXISTING,
            $partnerId,
            $label,
            $trackingNumber,
            $providerStatus,
            self::ERROR_ORDER_ID_EXIST,
            $message
        );
    }

    public static function businessRejection(?string $errorCode, ?string $message): self
    {
        return new self(self::KIND_BUSINESS_REJECTION, null, null, null, null, $errorCode, $message);
    }

    public static function malformed(?string $message = null): self
    {
        return new self(self::KIND_MALFORMED, null, null, null, null, null, $message);
    }

    /** KIND_* constant. */
    public function getKind(): string
    {
        return $this->kind;
    }

    /** Provider-side partner reference (order.id echo / duplicate partner_id) — nullable. */
    public function getPartnerId(): ?string
    {
        return $this->partnerId;
    }

    /** GHTK label code (order.label; duplicate: ghtk_label) — nullable. */
    public function getLabel(): ?string
    {
        return $this->label;
    }

    /** Tracking identifier per the TASK-KCXKVR precedence — nullable. */
    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    /** Raw provider status code/text when present — diagnostics only, NOT lifecycle. */
    public function getProviderStatus(): ?string
    {
        return $this->providerStatus;
    }

    /** GHTK error_code — BUSINESS_REJECTION / DUPLICATE_EXISTING only. */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /** GHTK message — diagnostics, never control flow. */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /** Usable shipment identity for the native label flow (label and/or tracking). */
    public function hasUsableIdentity(): bool
    {
        return ($this->label !== null && $this->label !== '')
            || ($this->trackingNumber !== null && $this->trackingNumber !== '');
    }
}
