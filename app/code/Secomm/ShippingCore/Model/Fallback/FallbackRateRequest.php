<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Fallback;

use Secomm\ShippingCore\Api\Fallback\FallbackRateRequestInterface;

/**
 * TASK-XXBN5X — immutable, self-guarding fallback request VO; @see FallbackRateRequestInterface.
 */
final class FallbackRateRequest implements FallbackRateRequestInterface
{
    /**
     * @param string|null $countryId destination ISO country id
     * @param int|null $regionId destination Magento region id
     * @param string|null $postcode destination postcode as supplied
     * @param float $weight cart weight (>= 0)
     * @param float $subtotal cart subtotal (>= 0)
     * @param float $qty total item quantity (>= 0)
     * @param int $storeId store scope
     * @param int|null $customerGroupId customer group id; null when unknown
     * @throws \LogicException on a negative cart dimension
     */
    public function __construct(
        private readonly ?string $countryId,
        private readonly ?int $regionId,
        private readonly ?string $postcode,
        private readonly float $weight,
        private readonly float $subtotal,
        private readonly float $qty,
        private readonly int $storeId,
        private readonly ?int $customerGroupId = null
    ) {
        if ($weight < 0 || $subtotal < 0 || $qty < 0) {
            throw new \LogicException('Fallback rate request cart dimensions must not be negative.');
        }
    }

    public function getCountryId(): ?string
    {
        return $this->countryId;
    }

    public function getRegionId(): ?int
    {
        return $this->regionId;
    }

    public function getPostcode(): ?string
    {
        return $this->postcode;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    public function getQty(): float
    {
        return $this->qty;
    }

    public function getStoreId(): int
    {
        return $this->storeId;
    }

    public function getCustomerGroupId(): ?int
    {
        return $this->customerGroupId;
    }
}
