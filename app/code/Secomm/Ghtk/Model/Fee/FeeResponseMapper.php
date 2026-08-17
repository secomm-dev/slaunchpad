<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Fee;

/**
 * Defensively maps the GHTK fee response to a FeeResult (AC-007). Encapsulates any
 * response-contract drift here — if the real API differs from the VN doc, only this
 * class changes (DEC-022 Q2 external verify still pending for exact field names).
 */
class FeeResponseMapper
{
    /**
     * @param array $response Decoded GHTK response.
     */
    public function map(array $response): ?FeeResult
    {
        $feeBlock = $response['fee'] ?? null;
        if (!is_array($feeBlock)) {
            return null;
        }

        return new FeeResult(
            $this->float($feeBlock['fee'] ?? null),
            $this->float($feeBlock['insurance_fee'] ?? null),
            $this->float($feeBlock['extFees'] ?? null),
            $this->bool($feeBlock['delivery'] ?? null),
            isset($feeBlock['name']) ? (string) $feeBlock['name'] : null
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
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}
