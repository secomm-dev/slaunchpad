<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Fallback;

use Secomm\ShippingCore\Api\Fallback\FallbackPolicyInterface;

/**
 * TASK-M3ME32 — DI-mapped fallback policy; @see FallbackPolicyInterface.
 *
 * Contribute entries via the `fallbackEnabledByLevel` DI array argument
 * (e.g. ['LEVEL_A' => true, 'LEVEL_B' => false]). Any level not listed is DISABLED — emergency
 * pricing must be opted into per service level. Only the boolean map is supported: no rules
 * engine, no conditions, no time windows, no price caps (extend only with concrete evidence).
 */
final class ConfigurableFallbackPolicy implements FallbackPolicyInterface
{
    /**
     * @param array<string, bool> $fallbackEnabledByLevel service-level code → enabled
     */
    public function __construct(
        private readonly array $fallbackEnabledByLevel = []
    ) {
    }

    public function isEnabled(string $serviceLevelCode): bool
    {
        return $this->fallbackEnabledByLevel[$serviceLevelCode] ?? false;
    }
}
