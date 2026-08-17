<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Api;

/**
 * Normalized, carrier-neutral shipping context (SL-015 / DEC-SL015-001).
 *
 * Scalar-only immutable snapshot of "what is being shipped / who is asking" —
 * deliberately free of mutable Magento models. Built per flow: the rate path
 * via ShippingContextFactory::fromRateRequest(); the (future) shipment path
 * builds the same context from a shipment, so rate and order submission share
 * one origin resolution.
 */
interface ShippingContextInterface
{
    public function getStoreId(): ?int;

    public function getWebsiteId(): ?int;

    public function getCarrierCode(): ?string;

    public function getQuoteId(): ?int;

    /**
     * Fulfillment source hint (e.g. MSI source code). Null unless a module
     * that knows about sources supplied one — the shipping core never
     * selects a source itself.
     */
    public function getSourceCode(): ?string;
}
