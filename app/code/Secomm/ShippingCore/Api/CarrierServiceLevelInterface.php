<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api;

/**
 * TASK-XXBN5X r1 (Phase E-SL0, SPIKE-WHHEZV §7) — what a carrier declares about the shipping
 * service levels it can provide. Implemented by carrier modules; ShippingCore only READS it —
 * a carrier never knows about fallback providers or rate sources (engineering rule: carriers
 * declare service levels but do not know fallback provider implementations).
 *
 * Values are DYNAMIC service-level machine codes — the set of valid codes is owned by
 * project/composition registration (ShippingServiceLevelRegistry), never by ShippingCore
 * constants. PORTABILITY IMPLICATION (documented, r1): a carrier module returning a literal
 * code (e.g. ['STANDARD']) is making a project/business declaration that only matches
 * compositions registering that same code — a composition with a different taxonomy would see
 * an explicit unknown-code configuration error. The long-term preferred model is CONFIGURED
 * carrier→service-level membership rather than compile-time business taxonomy inside reusable
 * carrier code; do not implement carrier config until a concrete carrier integration needs it
 * (r1 does not modify any carrier module).
 *
 * KNOWN LIMITATION (deliberate, SPIKE-WHHEZV directive §5): the declaration is carrier-level.
 * Carrier-level service declaration is sufficient for the current Launchpad architecture;
 * introduce method/operation-specific capability only when a concrete carrier proves it necessary.
 */
interface CarrierServiceLevelInterface
{
    /**
     * Service levels this carrier can provide, as REGISTERED machine codes — e.g. a standard
     * parcel carrier → ['STANDARD']; a same-day courier → ['EXPRESS', 'SAME_DAY'].
     *
     * @return string[] registered service-level machine codes (never display labels)
     */
    public function getServiceLevels(): array;
}
