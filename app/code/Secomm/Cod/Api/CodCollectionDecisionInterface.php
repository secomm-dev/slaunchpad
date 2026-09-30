<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Cod\Api;

/**
 * TASK-DFGFZ9 phase 2 (DEC-TASKDFGFZ9-002) — the SINGLE collection decision a carrier needs
 * when it is about to create a provider shipment: is this order COD, how much must the
 * provider collect, in which currency — or an explicit rejection with a reason.
 *
 * The contract is POLICY-AGNOSTIC: it carries a decision, it never hardcodes one. The P1
 * rules (one COD-collecting shipment per order, full order grand total, VND-only, partial
 * shipments rejected) live in the configured implementation
 * (`Secomm\Cod\Model\SingleCollectionCodResolver`) and can be swapped via a DI preference
 * without any contract change. Allocation across multiple shipments is a future per-client
 * solution and is deliberately absent.
 *
 * Consumers (carriers) only MAP the result to provider fields — they never read
 * `grand_total`/`base_total_due` themselves and never identify COD via their own config.
 */
interface CodCollectionDecisionInterface
{
    public const STATUS_COLLECTIBLE = 'collectible';

    public const STATUS_NOT_COD = 'not_cod';

    public const STATUS_REJECTED = 'rejected';

    /** Order currency is not supported by the provider (no conversion is performed). */
    public const REASON_CURRENCY_UNSUPPORTED = 'currency_unsupported';

    /** The shipment does not cover the full shippable quantity — a COD collection rule rejected it. */
    public const REASON_PARTIAL_SHIPMENT = 'partial_shipment';

    /** A different provider shipment of this order already collected (or its outcome is uncertain). */
    public const REASON_COD_ALREADY_COLLECTED = 'cod_already_collected';

    /** Order grand_total is negative — the collection amount is unusable (data defect). */
    public const REASON_INVALID_ORDER_AMOUNT = 'invalid_order_amount';

    public function getStatus(): string;

    public function isCollectible(): bool;

    public function isNotCod(): bool;

    public function isRejected(): bool;

    /**
     * Collectible amount in the decision currency. Null unless the status is COLLECTIBLE.
     */
    public function getAmount(): ?float;

    /**
     * Currency of the amount (the order currency). Null unless the status is COLLECTIBLE.
     */
    public function getCurrencyCode(): ?string;

    /**
     * Machine-readable rejection reason (REASON_*). Null unless the status is REJECTED.
     */
    public function getRejectionReason(): ?string;

    /**
     * Untranslated human-facing rejection detail for logs/admin comments.
     * Null unless the status is REJECTED.
     */
    public function getRejectionMessage(): ?string;
}
