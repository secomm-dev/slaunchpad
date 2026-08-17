<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Tracking;

/**
 * The single tracking-status processing pipeline (SL-017 / DEC-SL017-001).
 * Webhook receivers, Tracking API fetchers and reconciliation crons all build
 * a TrackingUpdateInterface and hand it HERE — no status business logic lives
 * anywhere else. Never throws for unresolvable/duplicate/out-of-order input;
 * always returns fast.
 */
interface CarrierTrackingProcessorInterface
{
    /**
     * @param TrackingUpdateInterface $update
     * @return bool True when the update resolved to a known shipment track
     *              (applied OR deliberately skipped as duplicate/out-of-order);
     *              false when no Magento track matches the carrier + number.
     */
    public function process(TrackingUpdateInterface $update): bool;
}
