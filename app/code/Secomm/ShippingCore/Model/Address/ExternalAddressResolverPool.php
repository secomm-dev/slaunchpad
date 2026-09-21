<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Address;

use Secomm\ShippingCore\Api\Address\ExternalAddressResolverInterface;

/**
 * DEC-FEATYA2C0W-004 (D7/D9 pattern) / TASK-AQT7V3 — DI-safe extension point for OPTIONAL
 * external disambiguation providers.
 *
 * Zero registered providers is the normal state: ShippingCore operates local-only until an
 * optional module (Secomm_VietMap, …) contributes a resolver via the
 * `externalAddressResolvers` DI array argument (see etc/di.xml). The pool is foundation only —
 * it aggregates and preserves registration order; calling resolvers (availability checks
 * included) is Phase E-B orchestration and deliberately does not happen here.
 */
final class ExternalAddressResolverPool
{
    private array $resolvers = [];

    /**
     * @param ExternalAddressResolverInterface[] $externalAddressResolvers contributed via DI
     * @throws \LogicException when a DI entry does not implement the resolver contract
     */
    public function __construct(array $externalAddressResolvers = [])
    {
        $resolvers = [];
        foreach ($externalAddressResolvers as $resolver) {
            if (!$resolver instanceof ExternalAddressResolverInterface) {
                throw new \LogicException(
                    sprintf(
                        'External address resolver must implement %s, got %s.',
                        ExternalAddressResolverInterface::class,
                        get_debug_type($resolver)
                    )
                );
            }
            $resolvers[] = $resolver;
        }
        $this->resolvers = $resolvers;
    }

    /**
     * Registered resolvers in DI registration order (never re-ordered, never filtered).
     *
     * @return ExternalAddressResolverInterface[]
     */
    public function getResolvers(): array
    {
        return $this->resolvers;
    }
}
