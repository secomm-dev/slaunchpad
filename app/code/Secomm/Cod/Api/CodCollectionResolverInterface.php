<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Cod\Api;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;

/**
 * TASK-DFGFZ9 phase 2/3 (DEC-TASKDFGFZ9-002/003) — ONE entry point answering "when this
 * provider shipment is created, does the provider collect cash, how much, in which currency
 * — or is the collection rejected?".
 *
 * Carriers call this when building a provider CREATE/order-submit request and MAP the
 * decision to provider fields (GHTK `pick_money`, GHN `cod_amount`). Carriers must NOT read
 * `grand_total`/`base_total_due` themselves and must NOT keep their own COD identification.
 *
 * The P1 implementation (`Secomm\Cod\Model\SingleCollectionCodResolver`) consults the
 * collection ledger ITSELF (frozen replay + the one-collection-per-order prior check across
 * carriers; the claim is engine-enforced via `active_order_claim` UNIQUE) — a caller cannot
 * bypass the rule by what it does or does not pass in. The decision carries the ORDER
 * currency; currency SUPPORT is a carrier concern (GHTK/GHN gate VND before
 * recordPending/POST — no conversion).
 *
 * `$attempt` identifies the CURRENT submit attempt and is REQUIRED for carrier flows: it
 * enables the frozen-amount replay and excludes the attempt's own ledger row from the prior
 * check (a same-shipment retry must not block itself). `null` is reserved for non-carrier
 * consumers — the frozen replay is skipped and the prior check runs excluding nothing, so a
 * null caller is still held to the one-collection rule.
 *
 * Concrete model typehints (not the Sales Api\Data interfaces) are deliberate, matching the
 * existing carrier seam: the P1 partial-coverage check needs
 * `Shipment::getAllItems()` → `getOrderItemId()/getQty()` and
 * `OrderItem::getParentItem()/getQtyShipped()/getQtyOrdered()`, which the API data
 * interfaces do not expose.
 */
interface CodCollectionResolverInterface
{
    public function resolve(
        Order $order,
        Shipment $shipment,
        ?CodCollectionAttemptInterface $attempt
    ): CodCollectionDecisionInterface;
}
