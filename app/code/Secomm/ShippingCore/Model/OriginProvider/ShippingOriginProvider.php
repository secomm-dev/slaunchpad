<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\OriginProvider;

use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Secomm\ShippingCore\Api\OriginInterface;
use Secomm\ShippingCore\Api\OriginProviderInterface;
use Secomm\ShippingCore\Api\ShippingContextInterface;
use Secomm\ShippingCore\Model\Origin;

/**
 * Default origin provider (SL-015 / DEC-SL015-001): normalizes the Magento
 * Shipping Origin config (Stores → Sales → Shipping Settings → Origin) into
 * an OriginInterface value object, store-scoped by the context.
 *
 * VN 2-level model (DEC-020/025): ward lives in the native `city` config
 * value; regionId is preserved so carriers can normalize to carrier-specific
 * names via the canonical (region_id, ward_id) key; province carries the
 * region's default (English) name as a display fallback. district /
 * telephone / contactName have no Magento Shipping Origin counterpart and
 * stay null — enrichment is a decorator's job, never the carrier's.
 *
 * Returns a data snapshot only — never decides usability (DEC-021 stays in
 * the carrier).
 */
class ShippingOriginProvider implements OriginProviderInterface
{
    private const XML_PATH_ORIGIN = 'shipping/origin/';

    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private RegionFactory $regionFactory
    ) {
    }

    public function resolve(ShippingContextInterface $context): OriginInterface
    {
        $storeId = $context->getStoreId();

        $regionId = $this->intOrNull($this->getValue('region_id', $storeId));
        $province = null;
        if ($regionId !== null) {
            $region = $this->regionFactory->create()->load($regionId);
            $province = $this->stringOrNull($region->getName());
        }

        $street = trim(sprintf(
            '%s %s',
            (string) $this->getValue('street_line1', $storeId),
            trim((string) $this->getValue('street_line2', $storeId))
        ));

        return new Origin(
            sourceCode: null,
            countryId: $this->stringOrNull($this->getValue('country_id', $storeId)),
            regionId: $regionId,
            province: $province,
            district: null,
            ward: $this->stringOrNull($this->getValue('city', $storeId)),
            street: $street !== '' ? $street : null,
            postcode: $this->stringOrNull($this->getValue('postcode', $storeId)),
            telephone: null,
            contactName: null
        );
    }

    private function getValue(string $field, ?int $storeId): mixed
    {
        return $this->scopeConfig->getValue(self::XML_PATH_ORIGIN . $field, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        $value = trim((string) $value);
        return $value !== '' && ctype_digit($value) ? (int) $value : null;
    }
}
