<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Origin;

use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\ShippingCore\Api\OriginInterface;
use Secomm\ShippingCore\Api\OriginProviderInterface;
use Secomm\ShippingCore\Api\ShippingContextInterface;
use Secomm\ShippingCore\Model\Origin;

/**
 * GHTK backward-compatible origin chain (SL-015 / DEC-SL015-001).
 *
 * 1. Any legacy `carriers/ghtk/pick_*` field set → legacy Origin wins
 *    (existing merchants keep today's behaviour; pick_address_id rides in
 *    carrier metadata). A HALF-filled legacy config is deliberately NOT
 *    delegated further — the strict pickup gate (DEC-021) then hides the
 *    carrier rather than silently shipping from a different warehouse.
 * 2. All legacy pickup fields empty → delegate to the inner
 *    OriginProviderInterface (default: Magento Shipping Origin; a future
 *    Secomm_ShippingFulfillment swaps that preference to an MSI source).
 */
class GhtkOriginProvider implements OriginProviderInterface
{
    public const METADATA_PICK_ADDRESS_ID = 'ghtk.pick_address_id';

    public function __construct(
        private GhtkConfig $config,
        private OriginProviderInterface $originProvider
    ) {
    }

    public function resolve(ShippingContextInterface $context): OriginInterface
    {
        $storeId = $context->getStoreId();
        $pickAddressId = $this->config->getPickAddressId($storeId);
        $province = $this->config->getPickProvince($storeId);
        $district = $this->config->getPickDistrict($storeId);
        $ward = $this->config->getPickWard($storeId);

        if ($pickAddressId === '' && $province === '' && $district === '' && $ward === '') {
            return $this->originProvider->resolve($context);
        }

        return new Origin(
            sourceCode: null,
            countryId: 'VN', // GHTK is a VN-only carrier (AC-014 gate handles destination side)
            regionId: null,
            province: $province !== '' ? $province : null,
            district: $district !== '' ? $district : null,
            ward: $ward !== '' ? $ward : null,
            street: null,
            postcode: null,
            telephone: null,
            contactName: null,
            metadata: $pickAddressId !== '' ? [self::METADATA_PICK_ADDRESS_ID => $pickAddressId] : []
        );
    }
}
