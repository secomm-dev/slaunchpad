<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model;

use Secomm\ShippingCore\Api\ShippingContextInterface;

/**
 * Immutable scalar DTO — see ShippingContextInterface. Built via
 * ShippingContextFactory (rate path) or directly (shipment path later).
 */
final class ShippingContext implements ShippingContextInterface
{
    public function __construct(
        private readonly ?int $storeId,
        private readonly ?int $websiteId,
        private readonly ?string $carrierCode,
        private readonly ?int $quoteId,
        private readonly ?string $sourceCode
    ) {
    }

    public function getStoreId(): ?int
    {
        return $this->storeId;
    }

    public function getWebsiteId(): ?int
    {
        return $this->websiteId;
    }

    public function getCarrierCode(): ?string
    {
        return $this->carrierCode;
    }

    public function getQuoteId(): ?int
    {
        return $this->quoteId;
    }

    public function getSourceCode(): ?string
    {
        return $this->sourceCode;
    }
}
