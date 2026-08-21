<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PromotionMaxDiscount\Test\Integration;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\SalesRule\Model\Rule;
use PHPUnit\Framework\TestCase;

/**
 * Shared engine for the cap integration tests (Option 2 — dev DB):
 * OM access, fixture registry with guaranteed cleanup, precision-aware
 * float comparison, quote/recalc + breakdown readers.
 */
abstract class AbstractCapTestCase extends TestCase
{
    private const SKU_PREFIX = 'SMD-IT-';

    /** @var ObjectManagerInterface */
    protected static ObjectManagerInterface $om;

    /** @var array<string, \Magento\Catalog\Api\Data\ProductInterface> */
    protected array $products = [];

    /** @var array<int, Rule> */
    protected array $rules = [];

    /** @var array<int, Quote> */
    protected array $quotes = [];

    protected function setUp(): void
    {
        self::$om = $GLOBALS['__integrationOm'];
    }

    protected function tearDown(): void
    {
        foreach ($this->quotes as $quote) {
            try {
                if ($quote->getId()) {
                    $this->om(CartRepositoryInterface::class)->delete($quote);
                }
            } catch (\Throwable $e) {
                fwrite(STDERR, 'quote cleanup: ' . $e->getMessage() . "\n");
            }
        }
        foreach ($this->rules as $rule) {
            try {
                if ($rule->getId()) {
                    $rule->getResource()->delete($rule);
                }
            } catch (\Throwable $e) {
                fwrite(STDERR, 'rule cleanup: ' . $e->getMessage() . "\n");
            }
        }
        $registry = self::$om->get(\Magento\Framework\Registry::class);
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', true);
        foreach (array_reverse($this->products) as $sku => $product) {
            try {
                $repo = $this->om(ProductRepositoryInterface::class);
                $repo->delete($repo->get($sku));
            } catch (\Throwable $e) {
                fwrite(STDERR, "product cleanup $sku: " . $e->getMessage() . "\n");
            }
        }
        try {
            $conn = self::$om->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
            // products are gone by now — sweep their detached inventory rows
            $conn->query(
                "DELETE FROM inventory_source_item WHERE sku LIKE '" . self::SKU_PREFIX . "%'"
                . " AND sku NOT IN (SELECT sku FROM catalog_product_entity)"
            );
        } catch (\Throwable $e) {
            fwrite(STDERR, 'residue sweep: ' . $e->getMessage() . "\n");
        }
    }

    /**
     * @template T
     * @param class-string<T> $type
     * @return T
     */
    protected function om(string $type)
    {
        return self::$om->get($type);
    }

    /**
     * Tolerance is half a unit of the currency's smallest increment, so
     * amounts rounded to the currency grid compare stably (never naive float ==).
     */
    protected static function assertAmountEquals(
        float $expected,
        float $actual,
        int $precision,
        string $message = ''
    ): void {
        self::assertEqualsWithDelta($expected, $actual, 0.5 * (10 ** -$precision) + 1e-9, $message);
    }

    protected static function childSku(string $key): string
    {
        return self::SKU_PREFIX . $key . '-' . substr(md5((string) getmypid() . uniqid($key, true)), 0, 6);
    }

    /**
     * Fixture simple product with MSI salability (TASK-5H8WKE lesson 1:
     * source item + cataloginventory reindex + repository reload).
     */
    protected function makeProduct(string $key, float $price): \Magento\Catalog\Api\Data\ProductInterface
    {
        $sku = self::SKU_PREFIX . $key . '-' . substr(md5((string) getmypid() . uniqid($key, true)), 0, 6);
        $repo = $this->om(ProductRepositoryInterface::class);
        $factory = $this->om(\Magento\Catalog\Api\Data\ProductInterfaceFactory::class);
        $store = $this->om(\Magento\Store\Model\StoreManagerInterface::class)->getStore();

        $product = $factory->create();
        $product->setTypeId('simple')
            ->setAttributeSetId($product->getResource()->getEntityType()->getDefaultAttributeSetId())
            ->setWebsiteIds([$store->getWebsiteId()])
            ->setName('IT ' . $key)
            ->setSku($sku)
            ->setPrice($price)
            ->setStatus(1)
            ->setVisibility(4)
            ->setStockData(['qty' => 1000, 'is_in_stock' => 1, 'manage_stock' => 1]);
        $product = $repo->save($product);

        $si = $this->om(\Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory::class)->create();
        $si->setSku($sku);
        $si->setSourceCode('default');
        $si->setQuantity(1000);
        $si->setStatus(\Magento\InventoryApi\Api\Data\SourceItemInterface::STATUS_IN_STOCK);
        $this->om(\Magento\InventoryApi\Api\SourceItemsSaveInterface::class)->execute([$si]);

        $this->om(\Magento\Framework\Indexer\IndexerRegistry::class)
            ->get('cataloginventory_stock')
            ->reindexList([(int)$product->getId()]);

        $fresh = $repo->get($sku);
        $this->assertTrue($fresh->isSalable(), "fixture $sku must be salable (MSI + reindex + reload)");
        $this->products[$sku] = $fresh;
        return $fresh;
    }

    /**
     * Fixture cart rule. SKU restriction follows TASK-5H8WKE lesson 3:
     * actions conditions must be set as JSON via setActionsSerialized —
     * the object-graph addCondition() does not survive the resource save.
     */
    protected function makeRule(
        string $name,
        string $action,
        float $amount,
        ?float $cap,
        array $skus = [],
        bool $stopRules = false,
        ?string $couponCode = null
    ): Rule {
        $store = $this->om(\Magento\Store\Model\StoreManagerInterface::class)->getStore();
        $rule = $this->om(\Magento\SalesRule\Model\RuleFactory::class)->create();
        $rule->setName('IT-CAP ' . $name . ' ' . uniqid())
            ->setIsActive(1)
            ->setCouponType($couponCode ? Rule::COUPON_TYPE_SPECIFIC : Rule::COUPON_TYPE_NO_COUPON)
            ->setWebsiteIds([$store->getWebsiteId()])
            ->setCustomerGroupIds([0])
            ->setSimpleAction($action)
            ->setDiscountAmount($amount)
            ->setStopRulesProcessing($stopRules ? 1 : 0)
            ->setMaximumDiscountAmount($cap);
        if ($couponCode) {
            $rule->setCouponCode($couponCode);
        }
        if ($skus) {
            $rule->setActionsSerialized(json_encode([
                'type' => \Magento\SalesRule\Model\Rule\Condition\Product\Combine::class,
                'aggregator' => 'any',
                'value' => '1',
                'new_child' => '',
                'conditions' => [[
                    'type' => \Magento\SalesRule\Model\Rule\Condition\Product::class,
                    'attribute' => 'sku',
                    'operator' => '()',
                    'value' => implode(',', $skus),
                    'is_value_processed' => false,
                ]],
            ]));
        }
        $rule->getResource()->save($rule);

        $persisted = (string)$rule->getResource()->getConnection()->fetchOne(
            'select actions_serialized from salesrule where rule_id = ?',
            [(int)$rule->getId()]
        );
        if ($skus && !str_contains($persisted, array_values($skus)[0])) {
            throw new \RuntimeException("actions conditions did not persist for $name");
        }
        $this->rules[(int)$rule->getId()] = $rule;
        return $rule;
    }

    /**
     * Fixture quote, persisted BEFORE any breakdown assertion (TASK-5H8WKE
     * lesson 2: unsaved items have NULL ids and collide the native
     * RulesApplier breakdown aggregator).
     */
    protected function makeQuote(array $products, array $qtys = []): Quote
    {
        $store = $this->om(\Magento\Store\Model\StoreManagerInterface::class)->getStore();
        $quote = $this->om(\Magento\Quote\Model\QuoteFactory::class)->create();
        $quote->setStore($store)->setCustomerIsGuest(true)->setCustomerGroupId(0);
        foreach ($products as $product) {
            $qty = $qtys[$product->getSku()] ?? 1;
            $quote->addProduct($product, $qty);
        }
        $quote->getShippingAddress()->setCountryId('VN');
        $this->om(CartRepositoryInterface::class)->save($quote);
        $this->quotes[(int)$quote->getId()] = $quote;
        return $quote;
    }

    /**
     * Guest checkout finalisation shared by the lifecycle tests: complete
     * addresses, flatrate shipping, checkmo payment, persist. flatrate and
     * checkmo are active in the dev install.
     */
    protected function finalizeGuestOrder(Quote $quote): void
    {
        $fields = [
            'firstname' => 'IT', 'lastname' => 'Cap',
            'email' => 'it-cap@example.com', 'telephone' => '0900000000',
            'street' => ['1 Test St'], 'city' => 'Ha Noi', 'postcode' => '100000',
            'country_id' => 'VN',
        ];
        foreach ($fields as $key => $value) {
            $quote->getShippingAddress()->setDataUsingMethod($key, $value);
            $quote->getBillingAddress()->setDataUsingMethod($key, $value);
        }
        $quote->setCustomerEmail('it-cap@example.com');
        $quote->getShippingAddress()->setCollectShippingRates(true)->setShippingMethod('flatrate_flatrate');
        $quote->getPayment()->setMethod('checkmo');
        $this->om(CartRepositoryInterface::class)->save($quote);
    }

    protected function recalc(Quote $quote): void
    {
        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();
    }

    /**
     * @return array<int, \Magento\Quote\Model\Quote\Item\AbstractItem> sku => item
     */
    protected function visibleItems(Quote $quote): array
    {
        $map = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $map[$item->getSku()] = $item;
        }
        return $map;
    }

    /**
     * Address per-rule breakdown: rule_id => [base, display]
     *
     * @return array<int, array{base: float, display: float}>
     */
    protected function addressBreakdown(Quote $quote): array
    {
        $map = [];
        /** @var Address $address */
        $address = $quote->getShippingAddress();
        $discounts = $address->getExtensionAttributes() ? $address->getExtensionAttributes()->getDiscounts() : [];
        foreach ((array)$discounts as $entry) {
            $map[(int)$entry->getRuleID()] = [
                'base' => (float)$entry->getDiscountData()->getBaseAmount(),
                'display' => (float)$entry->getDiscountData()->getAmount(),
            ];
        }
        return $map;
    }

    /**
     * Currency precision of the quote (base currency) — VND store = 0.
     */
    protected function precision(Quote $quote): int
    {
        $format = $this->om(\Magento\Framework\Locale\FormatInterface::class)
            ->getPriceFormat(null, $quote->getBaseCurrencyCode() ?: $quote->getStore()->getBaseCurrencyCode());
        return max(0, min(4, (int)($format['precision'] ?? 2)));
    }
}
