<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PromotionMaxDiscount\Test\Unit\Cap;

use Magento\SalesRule\Model\ResourceModel\Rule\Collection;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory;
use Magento\SalesRule\Model\Rule;
use PHPUnit\Framework\TestCase;
use Secomm\PromotionMaxDiscount\Model\Cap\RuleCapResolver;

/**
 * AC-3/AC-7/AC-10 — resolver guards and per-pass query behaviour.
 */
class RuleCapResolverTest extends TestCase
{
    /**
     * @return CollectionFactory
     */
    private function collectionFactory(Collection $collection): CollectionFactory
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        return $factory;
    }

    /**
     * @param int $ruleId
     * @param float|string|null $cap
     * @return Rule
     */
    private function rule(int $ruleId, $cap): Rule
    {
        $rule = $this->createMock(Rule::class);
        $rule->method('getId')->willReturn($ruleId);
        $rule->method('getData')->with('maximum_discount_amount')->willReturn($cap);
        return $rule;
    }

    public function testEmptyRuleIdsSkipTheQueryEntirely(): void
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects($this->never())->method('create');

        $resolver = new RuleCapResolver($factory);
        $this->assertSame([], $resolver->resolveCappedRules([]));
    }

    public function testReturnsOnlyPositiveCaps(): void
    {
        $collection = $this->createConfiguredMock(Collection::class, [
            'getItems' => [
                $this->rule(11, '50000.0000'),
                $this->rule(12, '0.0000'),
                $this->rule(13, null),
            ],
        ]);
        $collection->method('addFieldToSelect')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnSelf();

        $resolver = new RuleCapResolver($this->collectionFactory($collection));
        $this->assertSame([11 => 50000.0], $resolver->resolveCappedRules([11, 12, 13]));
    }

    public function testFiltersByPercentAndRuleIdsAtQueryTime(): void
    {
        $calls = [];
        $selects = [];
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToSelect')->willReturnCallback(
            function ($fields) use (&$selects, $collection) {
                $selects[] = $fields;
                return $collection;
            }
        );
        $collection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $condition) use (&$calls, $collection) {
                $calls[] = [$field, $condition];
                return $collection;
            }
        );
        $collection->method('getItems')->willReturn([]);

        $resolver = new RuleCapResolver($this->collectionFactory($collection));
        $resolver->resolveCappedRules([11, 12, 11]);

        $this->assertContains(['rule_id', ['in' => [11, 12]]], $calls);
        $this->assertContains(['simple_action', 'by_percent'], $calls);
        // pre-review W1: three columns only — no serialized conditions/actions
        // blobs on the per-pass query
        $this->assertContains(['rule_id', 'simple_action', 'maximum_discount_amount'], $selects);
    }

    public function testNoInstanceMemoisationAcrossCalls(): void
    {
        // The collector/resolver are shared DI instances; caching here would
        // leak stale caps across collect passes. The per-pass single query is
        // the collector's contract (one resolveCappedRules call per collect),
        // not instance state — see the resolver class docblock.
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToSelect')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getItems')->willReturn([$this->rule(21, 100.0)]);

        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects($this->exactly(2))->method('create')->willReturn($collection);

        $resolver = new RuleCapResolver($factory);
        $this->assertSame([21 => 100.0], $resolver->resolveCappedRules([21]));
        $this->assertSame([21 => 100.0], $resolver->resolveCappedRules([21]));
    }
}
