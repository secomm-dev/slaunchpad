<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api;

/**
 * Runtime shipping origin resolution (SL-015 / DEC-SL015-001) — the stable
 * extension contract between carriers and any future fulfillment module.
 *
 * Default implementation (ShippingOriginProvider) reads the Magento Shipping
 * Origin config. A module such as Secomm_ShippingFulfillment replaces it (DI
 * preference) or decorates it (plugin) to resolve an MSI-source-based origin —
 * carriers keep consuming OriginInterface unchanged.
 *
 * Implementations return a data snapshot and MUST NOT decide whether the
 * origin is usable: usability is carrier policy (e.g. GHTK's strict pickup
 * gate, DEC-021) and stays in the carrier.
 */
interface OriginProviderInterface
{
    public function resolve(ShippingContextInterface $context): OriginInterface;
}
