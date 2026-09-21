<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Fallback;

use Secomm\ShippingCore\Api\Fallback\FallbackRateProviderInterface;

/**
 * TASK-XXBN5X (SPIKE-WHHEZV §13) — DI-safe registration point for OPTIONAL fallback rate
 * providers; mirror of the ExternalAddressResolverPool D7 pattern.
 *
 * Zero registered providers is the normal state: ShippingCore operates without fallback pricing
 * until an optional third-party bridge module contributes a provider via the
 * `fallbackRateProviders` DI array argument. The current project need is exactly ONE provider —
 * the pool aggregates and preserves registration order; competition/chaining between providers
 * is deliberately NOT supported (extend only with concrete evidence).
 */
final class FallbackRateProviderPool
{
    private array $providers = [];

    /**
     * @param FallbackRateProviderInterface[] $fallbackRateProviders contributed via DI
     * @throws \LogicException when a DI entry does not implement the provider contract
     */
    public function __construct(array $fallbackRateProviders = [])
    {
        $providers = [];
        foreach ($fallbackRateProviders as $provider) {
            if (!$provider instanceof FallbackRateProviderInterface) {
                throw new \LogicException(
                    sprintf(
                        'Fallback rate provider must implement %s, got %s.',
                        FallbackRateProviderInterface::class,
                        get_debug_type($provider)
                    )
                );
            }
            $providers[] = $provider;
        }
        $this->providers = $providers;
    }

    /**
     * Registered providers in DI registration order (never re-ordered, never filtered).
     *
     * @return FallbackRateProviderInterface[]
     */
    public function getProviders(): array
    {
        return $this->providers;
    }
}
