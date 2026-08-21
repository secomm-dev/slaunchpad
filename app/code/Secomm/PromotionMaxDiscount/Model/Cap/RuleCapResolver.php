<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PromotionMaxDiscount\Model\Cap;

use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as RuleCollectionFactory;

/**
 * Resolves which rules carry an active cap for a collect pass
 * (SPEC-FEAT-JKZM68 §7 / DEC-FEATJKZM68-001 §3).
 *
 * Stateless on purpose: the collector and this resolver are shared DI
 * instances for the whole request, so a result memoised here would leak
 * across collect passes and go stale when a rule's cap changes between
 * passes (observed in the runtime smoke: pass 1 with cap NULL poisoned
 * pass 2 after the cap was set). The collector guarantees the per-pass
 * contract instead: it calls this method exactly once per collect() —
 * one query per pass, never inside the item loop (AC-10). Guards are
 * applied at query time, i.e. at calculation time: simple_action must be
 * by_percent right now (protects rules whose action was switched while a
 * cap value still sits in the DB) and the cap must be > 0 — NULL/0 mean
 * "unlimited" and fall back to native behavior (AC-3/AC-7).
 */
final class RuleCapResolver
{
    public const ACTION_BY_PERCENT = 'by_percent';

    /**
     * @var RuleCollectionFactory
     */
    private RuleCollectionFactory $ruleCollectionFactory;

    /**
     * @param RuleCollectionFactory $ruleCollectionFactory
     */
    public function __construct(RuleCollectionFactory $ruleCollectionFactory)
    {
        $this->ruleCollectionFactory = $ruleCollectionFactory;
    }

    /**
     * @param int[] $ruleIds candidate rule ids found in item discount breakdowns
     * @return array<int, float> rule_id => cap, only by_percent rules with cap > 0
     */
    public function resolveCappedRules(array $ruleIds): array
    {
        $ruleIds = array_values(array_unique(array_map('intval', $ruleIds)));
        if ($ruleIds === []) {
            return [];
        }

        $collection = $this->ruleCollectionFactory->create();
        // Three columns only — a full salesrule row carries the heavy
        // conditions/actions serialized blobs for nothing (plan §1.2).
        $collection->addFieldToSelect(['rule_id', 'simple_action', 'maximum_discount_amount']);
        $collection->addFieldToFilter('rule_id', ['in' => $ruleIds]);
        $collection->addFieldToFilter('simple_action', self::ACTION_BY_PERCENT);

        $caps = [];
        foreach ($collection->getItems() as $rule) {
            $cap = (float) $rule->getData('maximum_discount_amount');
            if ($cap > 0.0) {
                $caps[(int) $rule->getId()] = $cap;
            }
        }
        return $caps;
    }
}
