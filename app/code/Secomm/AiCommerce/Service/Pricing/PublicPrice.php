<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Service\Pricing;

use Magento\Bundle\Pricing\Price\FinalPrice as BundleFinalPrice;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;

/**
 * Deterministic guest-facing public price (plan rev 2 §2b "Public price").
 *
 * Public semantics — identical to what an anonymous storefront visitor sees
 * for the selected store, without cookie/session state:
 * - simple: FinalPrice (special-price aware); RegularPrice as regular_value
 *   when it differs.
 * - configurable: FinalPrice of the parent resolves to the minimum final
 *   price across variations (core ConfigurablePrice behavior).
 * - bundle: the bundle FinalPrice VALUE differs from the storefront-displayed
 *   minimum, so the bundle FinalPrice minimal AMOUNT is used — the same
 *   "as low as" value the storefront product page and its structured data
 *   emit (verified at runtime: sprite-yoga-strap3.html shows 14,00 € /
 *   "price":"14.00", matching FinalPrice::getMinimalPrice()).
 * - grouped: FinalPrice resolves to the minimum associated-product price.
 *
 * Currency determinism: the core pricing pipeline converts into the store's
 * CURRENT currency, which a visitor cookie could switch. The store default
 * currency is therefore pinned for the duration of the lookup and RESTORED
 * immediately afterwards — no request-global Store state is left mutated,
 * and the conversion happens exactly once inside the core pipeline (never a
 * second facade-side conversion).
 */
class PublicPrice
{
    private const EQUALS_EPSILON = 0.000001;

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
        $previousCurrency = $this->pinDefaultCurrency($store, $defaultCurrency);

        /** @var PriceInfoInterface $priceInfo */
        $priceInfo = $product->getPriceInfo();
        $finalPrice = $priceInfo->getPrice(FinalPrice::PRICE_CODE);

        $final = $this->finalValue($finalPrice);
        $regular = (float) $priceInfo->getPrice(RegularPrice::PRICE_CODE)->getValue();

        $this->restoreCurrency($store, $previousCurrency);

        return [
            'value' => (float) $final,
            'currency' => $defaultCurrency,
            'regular_value' => $regular > self::EQUALS_EPSILON
                && abs($regular - $final) > self::EQUALS_EPSILON
                ? $regular
                : null,
        ];
    }

    /**
     * Final price value with bundle minimal semantics.
     *
     * @param \Magento\Framework\Pricing\Price\PriceInterface $finalPrice resolved final price
     * @return float public final value
     */
    private function finalValue(\Magento\Framework\Pricing\Price\PriceInterface $finalPrice): float
    {
        if ($finalPrice instanceof BundleFinalPrice) {
            return (float) $finalPrice->getMinimalPrice()->getValue();
        }

        return (float) $finalPrice->getValue();
    }

    /**
     * Pin the store default currency for the lookup.
     *
     * @param StoreInterface $store store view scope
     * @param string $defaultCurrency store default currency code
     * @return string|null previous current currency code (for restore), null when not pinnable
     */
    private function pinDefaultCurrency(StoreInterface $store, string $defaultCurrency): ?string
    {
        if (!$store instanceof Store) {
            return null;
        }

        $previous = (string) $store->getCurrentCurrencyCode();
        $store->setCurrentCurrencyCode($defaultCurrency);

        return $previous;
    }

    /**
     * Restore the pre-lookup current currency.
     *
     * @param StoreInterface $store store view scope
     * @param string|null $previousCurrency previous currency code from pinDefaultCurrency()
     * @return void
     */
    private function restoreCurrency(StoreInterface $store, ?string $previousCurrency): void
    {
        if ($previousCurrency !== null && $store instanceof Store) {
            $store->setCurrentCurrencyCode($previousCurrency);
        }
    }
}
