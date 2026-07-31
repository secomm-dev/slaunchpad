<?php
/**
 * MoMo v2 signature helper.
 *
 * MoMo signs requests with HMAC-SHA256 over a "rawSignature" string of
 * `key=value` pairs joined by `&`, in a specific field order per operation.
 * This helper builds that string from an ordered map and signs it, so every
 * MoMo operation (create order, refund, IPN verify) reuses one tested path.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Helper;

class Signature
{
    /**
     * Build the rawSignature string from an ordered key=>value map.
     *
     * Order matters: the caller must pass keys in the exact order MoMo expects
     * for the given operation.
     *
     * @param array<string, scalar> $params
     * @return string
     */
    public function buildRawSignature(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $key . '=' . $value;
        }

        return implode('&', $parts);
    }

    /**
     * Sign an ordered param map with the secret key (HMAC-SHA256).
     *
     * @param array<string, scalar> $params
     * @param string $secretKey
     * @return string
     */
    public function sign(array $params, string $secretKey): string
    {
        return hash_hmac('sha256', $this->buildRawSignature($params), $secretKey);
    }

    /**
     * Verify a MoMo signature against an ordered param map.
     *
     * @param string $signature
     * @param array<string, scalar> $params
     * @param string $secretKey
     * @return bool
     */
    public function verify(string $signature, array $params, string $secretKey): bool
    {
        return hash_equals($this->sign($params, $secretKey), $signature);
    }
}
