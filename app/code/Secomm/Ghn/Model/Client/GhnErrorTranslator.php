<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Client;

use Secomm\Ghn\Api\Exception\GhnApiException;
use Secomm\Ghn\Api\Exception\ProviderAuthenticationException;
use Secomm\Ghn\Api\Exception\ProviderInvalidAddressException;
use Secomm\Ghn\Api\Exception\ProviderInvalidRequestException;
use Secomm\Ghn\Api\Exception\ProviderRateLimitException;
use Secomm\Ghn\Api\Exception\ProviderRateUnavailableException;
use Secomm\Ghn\Api\Exception\ProviderRemoteException;
use Secomm\Ghn\Api\Exception\ProviderServiceUnavailableException;

/**
 * SPEC-FEAT-FQWEQ3 §11 — translate raw GHN failures (envelope codes, HTTP statuses) into the
 * typed provider exception taxonomy. Raw GHN errors never leak past GhnApiClient; ShippingCore
 * only ever sees outcome statuses + failure reasons (translated later by the GHN-C rate adapter).
 *
 * Classification rules (deterministic, no guessing):
 *  - envelope/HTTP 401..403                      → Authentication
 *  - envelope/HTTP 400, 404                      → InvalidRequest
 *  - envelope/HTTP 429, 5xx                      → ServiceUnavailable
 *  - address-shaped messages (WARD/DISTRICT/
 *    PROVINCE/ADDRESS keywords)                  → InvalidAddress
 *  - rate-not-offered messages                   → RateUnavailable
 *  - anything else                               → Remote
 */
final class GhnErrorTranslator
{
    /** Message fragments that identify an address-shaped GHN rejection. */
    private const ADDRESS_FRAGMENTS = ['WARD', 'DISTRICT', 'PROVINCE', 'ADDRESS'];

    /** Message fragments that identify a "no rate for this route/service" business answer. */
    private const RATE_FRAGMENTS = ['SERVICE IS NOT READY', 'NO SERVICE', 'NOT AVAILABLE FOR'];

    /**
     * @throws GhnApiException always — the translated typed exception
     */
    public function translate(int $code, string $message, string $operation): GhnApiException
    {
        $message = trim($message);
        $detail = __('GHN %1 failed (provider code %2): %3', $operation, $code, $message);

        if ($this->containsFragment($message, self::RATE_FRAGMENTS)) {
            return new ProviderRateUnavailableException($detail);
        }

        if ($this->containsFragment($message, self::ADDRESS_FRAGMENTS)) {
            return new ProviderInvalidAddressException($detail);
        }

        if (in_array($code, [401, 402, 403], true)) {
            return new ProviderAuthenticationException($detail);
        }

        if (in_array($code, [400, 404], true)) {
            return new ProviderInvalidRequestException($detail);
        }

        // TASK-GKHXY1 r2: 429 is split from 5xx — a throttle rejects BEFORE processing (definitive
        // not-applied evidence for mutations), while a 5xx leaves the applied/not-applied question open.
        if ($code === 429) {
            return new ProviderRateLimitException($detail);
        }

        if ($code >= 500) {
            return new ProviderServiceUnavailableException($detail);
        }

        return new ProviderRemoteException($detail);
    }

    private function containsFragment(string $message, array $fragments): bool
    {
        $upper = strtoupper($message);
        foreach ($fragments as $fragment) {
            if (str_contains($upper, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
