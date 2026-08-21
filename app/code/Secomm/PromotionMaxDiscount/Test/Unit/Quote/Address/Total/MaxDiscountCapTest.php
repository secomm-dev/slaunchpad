<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PromotionMaxDiscount\Test\Unit\Quote\Address\Total;

use Magento\Quote\Api\Data\CartItemExtensionInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\SalesRule\Api\Data\DiscountDataInterface;
use Magento\SalesRule\Api\Data\DiscountDataInterfaceFactory;
use Magento\SalesRule\Api\Data\RuleDiscountInterface;
use Magento\SalesRule\Api\Data\RuleDiscountInterfaceFactory;
use PHPUnit\Framework\TestCase;
use Secomm\PromotionMaxDiscount\Model\Cap\RuleCapResolver;
use Secomm\PromotionMaxDiscount\Model\Quote\Address\Total\MaxDiscountCap;
use Secomm\PromotionMaxDiscount\Model\Redistribution\LargestRemainderAllocator;

/**
 * Collector behaviour with mocked items/breakdown (AC-2/AC-3/AC-5/AC-6/AC-7):
 * cap lands on both chains per contribution (asserted on the in-place
 * DiscountData mutation), no-op paths leave everything native, multi-rule
 * independence, repeat-collect determinism, address breakdown rebuilt.
 */
class MaxDiscountCapTest extends TestCase
{
    private const EPSILON = 1e-6;

    /**
     * @var \Magento\Framework\Locale\FormatInterface
     */
    private \Magento\Framework\Locale\FormatInterface $localeFormat;

    /**
     * @var DiscountDataInterfaceFactory
     */
    private DiscountDataInterfaceFactory $discountDataFactory;

    /**
     * @var RuleDiscountInterfaceFactory
     */
    private RuleDiscountInterfaceFactory $ruleDiscountFactory;

    protected function setUp(): void
    {
        $this->localeFormat = $this->createMock(\Magento\Framework\Locale\FormatInterface::class);
        $this->localeFormat->method('getPriceFormat')->willReturn(['precision' => 0]);

        $this->discountDataFactory = $this->createMock(DiscountDataInterfaceFactory::class);
        $this->discountDataFactory->method('create')->willReturnCallback(
            function (array $data = []) {
                $payload = $data['data'] ?? [];
                $discount = $this->createMock(DiscountDataInterface::class);
                $discount->method('getAmount')->willReturn($payload['amount'] ?? null);
                $discount->method('getBaseAmount')->willReturn($payload['base_amount'] ?? null);
                $discount->method('getOriginalAmount')->willReturn($payload['original_amount'] ?? null);
                $discount->method('getBaseOriginalAmount')
                    ->willReturn($payload['base_original_amount'] ?? null);
                return $discount;
            }
        );
        $this->ruleDiscountFactory = $this->createMock(RuleDiscountInterfaceFactory::class);
        $this->ruleDiscountFactory->method('create')->willReturnCallback(
            function (array $data = []) {
                $payload = $data['data'] ?? [];
                $entry = $this->createMock(RuleDiscountInterface::class);
                $entry->method('getRuleID')->willReturn($payload['rule_id'] ?? null);
                $entry->method('getRuleLabel')->willReturn($payload['rule'] ?? null);
                $entry->method('getDiscountData')->willReturn($payload['discount'] ?? null);
                return $entry;
            }
        );
    }

    /**
     * Real resolver (final class, exercised as-is) over a stubbed rule
     * collection: rule_id => cap map becomes the "query result".
     *
     * @param array<int, float> $caps
     * @return RuleCapResolver
     */
    private function resolverWithCaps(array $caps): RuleCapResolver
    {
        $items = [];
        foreach ($caps as $ruleId => $cap) {
            $rule = $this->createMock(\Magento\SalesRule\Model\Rule::class);
            $rule->method('getId')->willReturn($ruleId);
            $rule->method('getData')->with('maximum_discount_amount')->willReturn($cap);
            $items[] = $rule;
        }

        $collection = $this->createMock(\Magento\SalesRule\Model\ResourceModel\Rule\Collection::class);
        $collection->method('addFieldToSelect')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getItems')->willReturn($items);

        $factory = $this->createMock(\Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return new RuleCapResolver($factory);
    }

    /**
     * Quote with one shipping address holding the given items; the assignment
     * carries the same items (single-address flow as under OSC).
     *
     * @param AbstractItem[] $items
     * @return array{0: Quote, 1: ShippingAssignmentInterface, 2: Total, 3: Address}
     */
    private function harness(array $items): array
    {
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getExtensionAttributes', 'setBaseDiscountAmount'])
            // the other amount setters are magic DataObject accessors on Address
            ->addMethods([
                'setDiscountAmount', 'setSubtotalWithDiscount', 'setBaseSubtotalWithDiscount',
            ])
            ->getMock();
        $address->method('getExtensionAttributes')->willReturn(
            $this->createMock(\Magento\Quote\Api\Data\AddressExtensionInterface::class)
        );

        $shipping = $this->createMock(ShippingInterface::class);
        $shipping->method('getAddress')->willReturn($address);

        $assignment = $this->createMock(ShippingAssignmentInterface::class);
        $assignment->method('getShipping')->willReturn($shipping);
        $assignment->method('getItems')->willReturn($items);

        $quoteAddress = $this->createMock(Address::class);
        $quoteAddress->method('getAllItems')->willReturn($items);

        // currency codes are magic DataObject accessors on Quote (persisted by
        // beforeSave) — addMethods so the collector resolves precision from the
        // quote's currencies, never from the request scope
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllAddresses'])
            ->addMethods(['getBaseCurrencyCode', 'getQuoteCurrencyCode'])
            ->getMock();
        $quote->method('getAllAddresses')->willReturn([$quoteAddress]);
        $quote->method('getBaseCurrencyCode')->willReturn('VND');
        $quote->method('getQuoteCurrencyCode')->willReturn('VND');

        $total = new Total([], new \Magento\Framework\Serialize\Serializer\Json());
        $total->setSubtotal(1000000.0);
        $total->setBaseSubtotal(1000000.0);
        $total->setTotalAmount('discount', -100000.0);
        $total->setBaseTotalAmount('discount', -100000.0);

        return [$quote, $assignment, $total, $address];
    }

    /**
     * Item carrying one breakdown entry per rule. Returns the item plus the
     * per-rule DiscountData mocks so tests can expect the in-place mutation.
     *
     * @param int $itemId
     * @param array<int, array{base: float, display: float, original_base: float, original_display: float}> $ruleNatives
     * @param float $itemDiscount
     * @param float $itemBaseDiscount
     * @return array{0: AbstractItem, 1: array<int, DiscountDataInterface>}
     */
    private function item(int $itemId, array $ruleNatives, float $itemDiscount, float $itemBaseDiscount): array
    {
        // concrete Item; discount amount accessors and getNoDiscount are magic
        // DataObject accessors (data keys), the rest are real methods
        $item = $this->getMockBuilder(\Magento\Quote\Model\Quote\Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getParentItem', 'getExtensionAttributes', 'getId'])
            ->addMethods(
                ['getNoDiscount', 'getDiscountAmount', 'setDiscountAmount',
                 'getBaseDiscountAmount', 'setBaseDiscountAmount']
            )
            ->getMock();
        $item->method('getNoDiscount')->willReturn(false);
        $item->method('getParentItem')->willReturn(false);
        $item->method('getId')->willReturn($itemId);
        $item->method('getDiscountAmount')->willReturn($itemDiscount);
        $item->method('getBaseDiscountAmount')->willReturn($itemBaseDiscount);

        $entries = [];
        $discountData = [];
        foreach ($ruleNatives as $ruleId => $natives) {
            // separate method call per rule: each stub owns a private $state
            // (a loop-local $state would be one variable reassigned per
            // iteration — every by-ref closure would share the last values)
            $data = $this->statefulDiscountData($natives);
            $discountData[$ruleId] = $data;

            $entry = $this->createMock(RuleDiscountInterface::class);
            $entry->method('getRuleID')->willReturn($ruleId);
            $entry->method('getRuleLabel')->willReturn('Rule ' . $ruleId);
            $entry->method('getDiscountData')->willReturn($data);
            $entries[$ruleId] = $entry;
        }

        $extension = $this->createMock(CartItemExtensionInterface::class);
        $extension->method('getDiscounts')->willReturn(array_values($entries));
        $item->method('getExtensionAttributes')->willReturn($extension);

        return [$item, $discountData];
    }

    /**
     * Stateful DiscountData stub: the collector mutates entries in place and
     * the address rebuild reads them back, so getters must reflect setters.
     *
     * @param array{base: float, display: float, original_base: float, original_display: float} $natives
     * @return \Magento\SalesRule\Model\Data\DiscountData
     */
    private function statefulDiscountData(array $natives): \Magento\SalesRule\Model\Data\DiscountData
    {
        $state = $natives;
        $data = $this->createMock(\Magento\SalesRule\Model\Data\DiscountData::class);
        // by-reference capture: getters must see the setters' updates
        $data->method('getBaseAmount')->willReturnCallback(
            function () use (&$state) {
                return $state['base'];
            }
        );
        $data->method('getAmount')->willReturnCallback(
            function () use (&$state) {
                return $state['display'];
            }
        );
        $data->method('getOriginalAmount')->willReturnCallback(
            function () use (&$state) {
                return $state['original_display'];
            }
        );
        $data->method('getBaseOriginalAmount')->willReturnCallback(
            function () use (&$state) {
                return $state['original_base'];
            }
        );
        $data->method('setBaseAmount')->willReturnCallback(
            function (float $v) use (&$state) {
                $state['base'] = $v;
            }
        );
        $data->method('setAmount')->willReturnCallback(
            function (float $v) use (&$state) {
                $state['display'] = $v;
            }
        );
        $data->method('setOriginalAmount')->willReturnCallback(
            function (float $v) use (&$state) {
                $state['original_display'] = $v;
            }
        );
        $data->method('setBaseOriginalAmount')->willReturnCallback(
            function (float $v) use (&$state) {
                $state['original_base'] = $v;
            }
        );
        return $data;
    }

    private function collector(RuleCapResolver $resolver): MaxDiscountCap
    {
        return new MaxDiscountCap(
            $resolver,
            new LargestRemainderAllocator(),
            $this->localeFormat,
            $this->discountDataFactory,
            $this->ruleDiscountFactory
        );
    }

    /**
     * @return array{base: float, display: float, original_base: float, original_display: float}
     */
    private function natives(float $base, float $display): array
    {
        return [
            'base' => $base,
            'display' => $display,
            'original_base' => $base,
            'original_display' => $display,
        ];
    }

    public function testCapScalesBothChainsByContribution(): void
    {
        // rule 5, cap 50000: natives base/display 40000+60000
        // -> factor 0.5 -> 20000/30000 on both chains (spec AC-004)
        $resolver = $this->resolverWithCaps([5 => 50000.0]);

        [$itemA, $dataA] = $this->item(1, [5 => $this->natives(40000.0, 40000.0)], 40000.0, 40000.0);
        [$itemB, $dataB] = $this->item(2, [5 => $this->natives(60000.0, 60000.0)], 60000.0, 60000.0);

        $itemA->expects($this->once())->method('setDiscountAmount')->with(20000.0);
        $itemA->expects($this->once())->method('setBaseDiscountAmount')->with(20000.0);
        $itemB->expects($this->once())->method('setDiscountAmount')->with(30000.0);
        $itemB->expects($this->once())->method('setBaseDiscountAmount')->with(30000.0);

        [$quote, $assignment, $total, $address] = $this->harness([$itemA, $itemB]);
        $address->expects($this->once())->method('setDiscountAmount')->with(-50000.0);
        $this->collector($resolver)->collect($quote, $assignment, $total);

        // entry values mutated in place (stateful stubs)
        $this->assertEqualsWithDelta(20000.0, $dataA[5]->getBaseAmount(), self::EPSILON);
        $this->assertEqualsWithDelta(20000.0, $dataA[5]->getAmount(), self::EPSILON);
        $this->assertEqualsWithDelta(20000.0, $dataA[5]->getBaseOriginalAmount(), self::EPSILON);
        $this->assertEqualsWithDelta(30000.0, $dataB[5]->getBaseAmount(), self::EPSILON);
        $this->assertEqualsWithDelta(30000.0, $dataB[5]->getAmount(), self::EPSILON);

        // aggregates: -100000 native + 50000 reduction-back -> -50000
        $this->assertEqualsWithDelta(-50000.0, $total->getDiscountAmount(), self::EPSILON);
        $this->assertEqualsWithDelta(-50000.0, $total->getBaseDiscountAmount(), self::EPSILON);
        $this->assertEqualsWithDelta(950000.0, $total->getSubtotalWithDiscount(), self::EPSILON);
    }

    public function testNoOpWhenSumAtOrBelowCap(): void
    {
        $resolver = $this->resolverWithCaps([5 => 100000.0]);

        [$itemA, $dataA] = $this->item(1, [5 => $this->natives(40000.0, 40000.0)], 40000.0, 40000.0);
        [$itemB, $dataB] = $this->item(2, [5 => $this->natives(60000.0, 60000.0)], 60000.0, 60000.0);

        $itemA->expects($this->never())->method('setDiscountAmount');
        $itemB->expects($this->never())->method('setDiscountAmount');

        [$quote, $assignment, $total, $address] = $this->harness([$itemA, $itemB]);
        $this->collector($resolver)->collect($quote, $assignment, $total);

        // entries untouched
        $this->assertEqualsWithDelta(40000.0, $dataA[5]->getBaseAmount(), self::EPSILON);
        $this->assertEqualsWithDelta(60000.0, $dataB[5]->getBaseAmount(), self::EPSILON);
        $this->assertEqualsWithDelta(-100000.0, $total->getDiscountAmount(), self::EPSILON);
    }

    public function testNoOpWhenResolverFindsNoCappedRules(): void
    {
        // cap NULL/0 or simple_action != by_percent — filtered by the resolver
        $resolver = $this->resolverWithCaps([]);

        [$itemA, $dataA] = $this->item(1, [7 => $this->natives(90000.0, 90000.0)], 90000.0, 90000.0);
        $itemA->expects($this->never())->method('setDiscountAmount');

        [$quote, $assignment, $total, $address] = $this->harness([$itemA]);
        $this->collector($resolver)->collect($quote, $assignment, $total);

        $this->assertEqualsWithDelta(-100000.0, $total->getDiscountAmount(), self::EPSILON);
    }

    public function testCappedAndUncappedRulesAreIndependent(): void
    {
        // rule 5 capped at 30000 (natives 20000+40000), rule 7 uncapped
        $resolver = $this->resolverWithCaps([5 => 30000.0]);

        [$itemA, $dataA] = $this->item(
            1,
            [5 => $this->natives(20000.0, 20000.0), 7 => $this->natives(10000.0, 10000.0)],
            30000.0,
            30000.0
        );
        [$itemB, $dataB] = $this->item(2, [5 => $this->natives(40000.0, 40000.0)], 40000.0, 40000.0);

        // rule 5 factor 0.5 -> item A 10000 (+ rule 7 unchanged 10000) = 20000
        $itemA->expects($this->once())->method('setDiscountAmount')->with(20000.0);
        // rule 5 factor 0.5 -> item B 20000
        $itemB->expects($this->once())->method('setDiscountAmount')->with(20000.0);

        [$quote, $assignment, $total, $address] = $this->harness([$itemA, $itemB]);
        $this->collector($resolver)->collect($quote, $assignment, $total);

        $this->assertEqualsWithDelta(10000.0, $dataA[5]->getBaseAmount(), self::EPSILON);
        $this->assertEqualsWithDelta(10000.0, $dataA[7]->getBaseAmount(), self::EPSILON);
        $this->assertEqualsWithDelta(20000.0, $dataB[5]->getBaseAmount(), self::EPSILON);
        // native total -100000; reduction 30000 (rule 5 natives 60000 -> 30000)
        $this->assertEqualsWithDelta(-70000.0, $total->getDiscountAmount(), self::EPSILON);
    }

    public function testRepeatCollectIsDeterministic(): void
    {
        $resolver = $this->resolverWithCaps([5 => 30000.0]);

        $build = function (): array {
            [$itemA] = $this->item(1, [5 => $this->natives(20000.0, 20000.0)], 20000.0, 20000.0);
            [$itemB] = $this->item(2, [5 => $this->natives(40000.0, 40000.0)], 40000.0, 40000.0);
            return [$itemA, $itemB];
        };

        // two fresh native states (native reset re-established between passes)
        [$qa, $aa, $ta] = $this->harness($build());
        $this->collector($resolver)->collect($qa, $aa, $ta);
        [$qb, $ab, $tb] = $this->harness($build());
        $this->collector($resolver)->collect($qb, $ab, $tb);

        $this->assertEqualsWithDelta($ta->getDiscountAmount(), $tb->getDiscountAmount(), self::EPSILON);
        // harness seeds the native aggregate at -100000; reduction 30000 -> -70000
        $this->assertEqualsWithDelta(-70000.0, $ta->getDiscountAmount(), self::EPSILON);
        $this->assertEqualsWithDelta(-70000.0, $tb->getDiscountAmount(), self::EPSILON);
    }

    public function testAddressBreakdownRebuiltAfterCap(): void
    {
        $resolver = $this->resolverWithCaps([5 => 50000.0]);

        [$itemA] = $this->item(1, [5 => $this->natives(40000.0, 40000.0)], 40000.0, 40000.0);
        [$itemB] = $this->item(2, [5 => $this->natives(60000.0, 60000.0)], 60000.0, 60000.0);

        $rebuilt = [];
        $this->ruleDiscountFactory->expects($this->atLeastOnce())->method('create')->willReturnCallback(
            function (array $data = []) use (&$rebuilt) {
                $payload = $data['data'] ?? [];
                $entry = $this->createMock(RuleDiscountInterface::class);
                $entry->method('getRuleID')->willReturn($payload['rule_id'] ?? null);
                $entry->method('getRuleLabel')->willReturn($payload['rule'] ?? null);
                $entry->method('getDiscountData')->willReturn($payload['discount'] ?? null);
                $rebuilt[] = $payload;
                return $entry;
            }
        );

        [$quote, $assignment, $total, $address] = $this->harness([$itemA, $itemB]);
        $this->collector($resolver)->collect($quote, $assignment, $total);

        // one aggregated address entry for rule 5, capped at 50000
        $this->assertCount(1, $rebuilt);
        $this->assertSame(5, $rebuilt[0]['rule_id']);
        $this->assertEqualsWithDelta(50000.0, $rebuilt[0]['discount']->getBaseAmount(), self::EPSILON);
        $this->assertEqualsWithDelta(50000.0, $rebuilt[0]['discount']->getAmount(), self::EPSILON);
    }

    public function testAddressBreakdownKeepsEntriesOfItemsUntouchedByCap(): void
    {
        // pre-review C1: capped rule 5 exceeded on items A+B while item C only
        // carries the uncapped rule 7 — the rebuild must aggregate from ALL
        // assignment items (native $itemsAggregate set), not only capped ones,
        // or rule 7 disappears from the address breakdown (REST/GraphQL)
        $resolver = $this->resolverWithCaps([5 => 30000.0]);

        [$itemA] = $this->item(1, [5 => $this->natives(20000.0, 20000.0)], 20000.0, 20000.0);
        [$itemB] = $this->item(2, [5 => $this->natives(40000.0, 40000.0)], 40000.0, 40000.0);
        [$itemC] = $this->item(3, [7 => $this->natives(15000.0, 15000.0)], 15000.0, 15000.0);

        $rebuilt = [];
        $this->ruleDiscountFactory->expects($this->atLeastOnce())->method('create')->willReturnCallback(
            function (array $data = []) use (&$rebuilt) {
                $payload = $data['data'] ?? [];
                $entry = $this->createMock(RuleDiscountInterface::class);
                $entry->method('getRuleID')->willReturn($payload['rule_id'] ?? null);
                $entry->method('getRuleLabel')->willReturn($payload['rule'] ?? null);
                $entry->method('getDiscountData')->willReturn($payload['discount'] ?? null);
                $rebuilt[] = $payload;
                return $entry;
            }
        );

        [$quote, $assignment, $total, $address] = $this->harness([$itemA, $itemB, $itemC]);
        $this->collector($resolver)->collect($quote, $assignment, $total);

        $byRule = [];
        foreach ($rebuilt as $payload) {
            $byRule[$payload['rule_id']] = $payload;
        }

        // capped rule present, scaled to its cap...
        $this->assertArrayHasKey(5, $byRule);
        $this->assertEqualsWithDelta(30000.0, $byRule[5]['discount']->getBaseAmount(), self::EPSILON);
        // ...and the uncapped rule of the untouched item survives with its
        // native amount
        $this->assertArrayHasKey(7, $byRule);
        $this->assertEqualsWithDelta(15000.0, $byRule[7]['discount']->getBaseAmount(), self::EPSILON);

        // headline totals unaffected by the rebuild: native -100000 seeded,
        // rule 5 reduction 30000
        $this->assertEqualsWithDelta(-70000.0, $total->getDiscountAmount(), self::EPSILON);
    }

    public function testPrecisionIsResolvedFromQuoteCurrenciesNotRequestScope(): void
    {
        // pre-review C2: getPriceFormat resolves currency through the CURRENT
        // request scope when no code is passed — the quote's currencies must be
        // passed explicitly (Format::getPriceFormat takes two args only; a
        // third store arg would be silently ignored)
        $resolver = $this->resolverWithCaps([5 => 30000.0]);

        $this->localeFormat = $this->createMock(\Magento\Framework\Locale\FormatInterface::class);
        $this->localeFormat
            ->expects($this->exactly(2)) // base chain + display chain
            ->method('getPriceFormat')
            ->with(null, 'VND')
            ->willReturn(['precision' => 0]);

        [$itemA] = $this->item(1, [5 => $this->natives(20000.0, 20000.0)], 20000.0, 20000.0);
        [$itemB] = $this->item(2, [5 => $this->natives(40000.0, 40000.0)], 40000.0, 40000.0);

        [$quote, $assignment, $total, $address] = $this->harness([$itemA, $itemB]);
        $this->collector($resolver)->collect($quote, $assignment, $total);

        // cap still lands: rule 5 natives 60000 -> 30000
        $this->assertEqualsWithDelta(-70000.0, $total->getDiscountAmount(), self::EPSILON);
    }
}
