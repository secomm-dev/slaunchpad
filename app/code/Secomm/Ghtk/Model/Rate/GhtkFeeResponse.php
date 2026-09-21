<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Rate;

use Secomm\Ghtk\Model\Fee\FeeResult;

/**
 * TASK-W8SH0N — parsed GHTK RATE response, distinguishing the three contract
 * shapes the fee endpoint can return (SPIKE-A1DGPY §16/§17):
 *
 * - SUCCESS:      HTTP 200 + success=true + a structurally valid fee block
 *                 (numeric fee amount — a missing/non-numeric amount is NOT a
 *                 usable rate);
 * - BUSINESS_REJECTION: HTTP 200 + success=false + error_code/message — a real
 *                 business/API rejection, never a transport problem;
 * - MALFORMED:    HTTP 200 but structurally unusable (missing fee block, missing
 *                 amount) — unusable technical data, NOT a business unavailability.
 *
 * The distinction matters because SHIPPINGCORE fallback only fires for
 * TECHNICAL_FAILURE — collapsing rejection into malformed (or vice versa) either
 * triggers TableRate fallback for business-invalid addresses or loses it during
 * a provider outage.
 */
final class GhtkFeeResponse
{
    public const KIND_SUCCESS = 'SUCCESS';
    public const KIND_BUSINESS_REJECTION = 'BUSINESS_REJECTION';
    public const KIND_MALFORMED = 'MALFORMED';

    private function __construct(
        private readonly string $kind,
        private readonly ?FeeResult $fee = null,
        private readonly ?string $errorCode = null,
        private readonly ?string $message = null
    ) {
    }

    public static function success(FeeResult $fee): self
    {
        return new self(self::KIND_SUCCESS, $fee);
    }

    public static function businessRejection(?string $errorCode, ?string $message): self
    {
        return new self(self::KIND_BUSINESS_REJECTION, null, $errorCode, $message);
    }

    public static function malformed(): self
    {
        return new self(self::KIND_MALFORMED);
    }

    /** KIND_SUCCESS | KIND_BUSINESS_REJECTION | KIND_MALFORMED */
    public function getKind(): string
    {
        return $this->kind;
    }

    /** Parsed fee payload — KIND_SUCCESS only. */
    public function getFee(): ?FeeResult
    {
        return $this->fee;
    }

    /** GHTK error_code — KIND_BUSINESS_REJECTION only (diagnostic, never control flow). */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /** GHTK message — KIND_BUSINESS_REJECTION only (diagnostic). */
    public function getMessage(): ?string
    {
        return $this->message;
    }
}
