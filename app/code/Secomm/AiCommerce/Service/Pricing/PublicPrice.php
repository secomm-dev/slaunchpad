<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service\Pricing;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;

/**
 * Deterministic guest-facing public price (plan rev 2 §2b "Public price").
 *
 * The core AbstractPrice pipeline converts via the request "current"
 * currency, which a visitor cookie can switch. For a cookie-independent
 * agent contract the store's DEFAULT currency is forced onto the store for
 * the duration of the lookup, so PriceInfo amounts are guaranteed to be in
 * the store default display currency — one conversion inside the core
 * pipeline, never a second facade-side conversion (no double conversion).
 *
 * Guest semantics: no customer-group price is resolved here — the request
 * never carries a customer session, so PriceInfo returns public prices only.
 */
class PublicPrice
{
    /**
     * Resolve the deterministic public price.
     *
     * @param StoreInterface $store store view scope
     * @param ProductInterface $product product entity
     * @return array{value: float, currency: string, regular_value: ?float}
     */
    public function resolve(StoreInterface $store, ProductInterface $product): array
    {
        $defaultCurrency = (string) $store->getDefaultCurrencyCode();

        if ($store instanceof Store) {
            // Pin the currency for this request so the core pricing pipeline
            // converts into the deterministic store default currency.
            $store->setCurrentCurrencyCode($defaultCurrency);
        }

        /** @var PriceInfoInterface $priceInfo */
        $priceInfo = $product->getPriceInfo();

        $final = $priceInfo->getPrice(FinalPrice::PRICE_CODE)->getValue();
        $regular = $priceInfo->getPrice(RegularPrice::PRICE_CODE)->getValue();

        return [
            'value' => (float) $final,
            'currency' => $defaultCurrency,
            'regular_value' => $regular > 0.000001 && abs($regular - $final) > 0.000001
                ? (float) $regular
                : null,
        ];
    }
}
