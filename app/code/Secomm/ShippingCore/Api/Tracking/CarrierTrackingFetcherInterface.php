<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api\Tracking;

/**
 * TASK-7AJ3K8 — one carrier API fetch for the shared tracking reconciliation
 * ({@see \Secomm\ShippingCore\Model\Tracking\TrackingReconciliationService}). The carrier
 * maps its raw status through its own CarrierStatusMapperInterface and returns a fully
 * normalized TrackingUpdate; carrier status knowledge never enters ShippingCore.
 *
 * Return semantics:
 * - TrackingUpdateInterface — a fresh observation to feed the shared processor;
 * - null — the carrier responded but carries no usable status (nothing to sync);
 * - throws (anything) — fetch failed; the reconciliation logs and CONTINUES with the next item.
 */
interface CarrierTrackingFetcherInterface
{
    public function fetch(string $trackingNumber): ?TrackingUpdateInterface;
}
