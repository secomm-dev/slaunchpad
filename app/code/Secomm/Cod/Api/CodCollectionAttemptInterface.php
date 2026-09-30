<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Cod\Api;

/**
 * TASK-DFGFZ9 phase 3 (DEC-TASKDFGFZ9-003) — the CURRENT submit attempt's identity, built by
 * the caller (carrier) from its own deterministic idempotency key — no DB query involved.
 * Secomm_Cod maps it to ledger rows: `findFrozenAmount` (same reference = this attempt's own
 * frozen decision) and `findCollectedPrior` (any other reference of the same order = the
 * one-collection rule). Carriers report and identify; Secomm_Cod owns the collection history.
 */
interface CodCollectionAttemptInterface
{
    /**
     * Attempt carrier code (e.g. 'ghtk', 'ghn').
     */
    public function getCarrierCode(): string;

    /**
     * Deterministic provider shipment identity — GHTK partner_order_code / GHN client_order_code.
     */
    public function getProviderShipmentReference(): string;
}
