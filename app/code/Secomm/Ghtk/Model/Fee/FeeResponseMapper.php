<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Fee;

use Secomm\Ghtk\Model\Rate\GhtkFeeResponse;

/**
 * Parses the GHTK RATE response into a typed, three-kind result
 * (TASK-W8SH0N — SPIKE-A1DGPY §16/§17 semantics):
 *
 * - success=false                          → BUSINESS_REJECTION (error_code/message preserved);
 * - success=true + valid fee block         → SUCCESS (usable rate payload);
 * - structurally unusable success payload  → MALFORMED (unusable technical data — classified
 *                                            TECHNICAL_FAILURE downstream, never "business").
 *
 * A missing/non-numeric `fee.fee` is NOT accepted as a usable rate (§11) — unless
 * the payload explicitly states delivery=false (the documented business
 * "unsupported destination" answer), which parses as BUSINESS_REJECTION.
 *
 * Response-contract drift is encapsulated HERE — only this class changes if the
 * real payload differs from the docs.
 */
class FeeResponseMapper
{
    /**
     * @param array $response Decoded GHTK response (HTTP 200 path — transport
     *                        failures arrive as exceptions from the client).
     */
    public function parse(array $response): GhtkFeeResponse
    {
        if (($response['success'] ?? null) === false) {
            return GhtkFeeResponse::businessRejection(
                isset($response['error_code']) && is_scalar($response['error_code'])
                    ? (string) $response['error_code']
                    : null,
                isset($response['message']) && is_string($response['message']) && $response['message'] !== ''
                    ? $response['message']
                    : null
            );
        }

        $feeBlock = $response['fee'] ?? null;
        if (!is_array($feeBlock)) {
            return GhtkFeeResponse::malformed();
        }

        $delivery = $this->bool($feeBlock['delivery'] ?? null);

        // A usable rate REQUIRES a numeric amount — success payloads missing it are
        // structurally broken (§11), EXCEPT the documented business answer
        // delivery=false, which needs no amount at all.
        if (!isset($feeBlock['fee']) || !is_numeric($feeBlock['fee'])) {
            return $delivery === false
                ? GhtkFeeResponse::businessRejection(null, 'delivery denied')
                : GhtkFeeResponse::malformed();
        }

        return GhtkFeeResponse::success(
            new FeeResult(
                $this->float($feeBlock['fee']),
                $this->float($feeBlock['insurance_fee'] ?? null),
                $this->float($feeBlock['extFees'] ?? null),
                $delivery,
                isset($feeBlock['name']) && is_string($feeBlock['name']) ? $feeBlock['name'] : null
            )
        );
    }

    private function float(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        // Treat missing/null as "delivery denied" (safe default — no rate shown).
        return $value === 1 || $value === '1' || $value === 'true';
    }
}
