<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PromotionMaxDiscount\Model\Quote\Address\Total;

use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\SalesRule\Api\Data\DiscountDataInterfaceFactory;
use Magento\SalesRule\Api\Data\RuleDiscountInterfaceFactory;
use Secomm\PromotionMaxDiscount\Model\Cap\RuleCapResolver;
use Secomm\PromotionMaxDiscount\Model\Redistribution\LargestRemainderAllocator;

/**
 * Per-rule maximum discount cap collector (TASK-5H8WKE / SPEC-FEAT-JKZM68 §4–§11,
 * DEC-FEATJKZM68-001 §1/§2/§4/§6).
 *
 * Runs at sort_order 310 — right after the native SalesRule discount collector
 * (300) and before shipping/tax/grand-total — and only scales DOWN what native
 * already produced: per capped rule, when Σ native per-item contributions
 * (base chain, authoritative) exceeds the cap, every contribution is scaled by
 * factor = cap/Σ and re-rounded with largest-remainder so Σ final == cap on
 * both the base and the display chain. Native eligibility, stacking, minFix and
 * shipping discounts are never re-implemented or touched (AC-8).
 *
 * Idempotency (spec §11): the collector holds no state of its own — every pass
 * reads the breakdown that native just reset and recalculated, mutates only
 * in-memory quote items/address data, so repeat collectTotals() is a pure
 * function of quote state. No fetch() on purpose: the cap lives inside the
 * native "discount" total, no extra totals row is added (AC-1).
 */
class MaxDiscountCap extends AbstractTotal
{
    private const TOTAL_CODE_DISCOUNT = 'discount';

    /**
     * @var RuleCapResolver
     */
    private RuleCapResolver $capResolver;

    /**
     * @var LargestRemainderAllocator
     */
    private LargestRemainderAllocator $allocator;

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

    /**
     * @param RuleCapResolver $capResolver
     * @param LargestRemainderAllocator $allocator
     * @param \Magento\Framework\Locale\FormatInterface $localeFormat
     * @param DiscountDataInterfaceFactory $discountDataFactory
     * @param RuleDiscountInterfaceFactory $ruleDiscountFactory
     */
    public function __construct(
        RuleCapResolver $capResolver,
        LargestRemainderAllocator $allocator,
        \Magento\Framework\Locale\FormatInterface $localeFormat,
        DiscountDataInterfaceFactory $discountDataFactory,
        RuleDiscountInterfaceFactory $ruleDiscountFactory
    ) {
        $this->capResolver = $capResolver;
        $this->allocator = $allocator;
        $this->localeFormat = $localeFormat;
        $this->discountDataFactory = $discountDataFactory;
        $this->ruleDiscountFactory = $ruleDiscountFactory;
    }

    /**
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);

        /** @var Address $address */
        $address = $shippingAssignment->getShipping()->getAddress();

        // Candidate set: every parent-visible item across all addresses
        // (quote-level per-rule cap, D3) — mirrors how the native discount
        // collector gathers $items from $quote->getAllAddresses().
        $itemEntries = [];
        $itemByKey = [];
        $ruleIds = [];
        $fallbackKey = 0;
        foreach ($quote->getAllAddresses() as $quoteAddress) {
            foreach ($quoteAddress->getAllItems() as $item) {
                if ($item->getNoDiscount() || $item->getParentItem()) {
                    continue;
                }
                $discounts = $item->getExtensionAttributes()
                    ? $item->getExtensionAttributes()->getDiscounts()
                    : null;
                if (!$discounts) {
                    continue;
                }
                $itemKey = $item->getId() !== null ? (int) $item->getId() : --$fallbackKey;
                $itemByKey[$itemKey] = $item;
                foreach ($discounts as $entry) {
                    if ($entry->getDiscountData() === null) {
                        continue;
                    }
                    $ruleIds[(int) $entry->getRuleID()] = true;
                    $itemEntries[$itemKey][(int) $entry->getRuleID()] = $entry;
                }
            }
        }

        if (!$ruleIds) {
            return $this;
        }

        $cappedRules = $this->capResolver->resolveCappedRules(array_keys($ruleIds));
        if (!$cappedRules) {
            return $this;
        }

        $basePrecision = $this->currencyPrecision($this->baseCurrencyCode($quote));
        $displayPrecision = $this->currencyPrecision($this->quoteCurrencyCode($quote));
        $deltas = [];

        foreach ($cappedRules as $ruleId => $cap) {
            $baseNatives = [];
            $displayNatives = [];
            foreach ($itemEntries as $itemKey => $entries) {
                if (!isset($entries[$ruleId])) {
                    continue;
                }
                $data = $entries[$ruleId]->getDiscountData();
                $baseNatives[$itemKey] = (float) $data->getBaseAmount();
                $displayNatives[$itemKey] = (float) $data->getAmount();
            }

            $baseTotal = array_sum($baseNatives);
            // Base chain is authoritative (spec §7): at or under the cap —
            // including exactly equal (spec AC-003) — the rule keeps native
            // amounts on both chains, no redistribution.
            if ($baseTotal <= 0.0 || $baseTotal <= $cap) {
                continue;
            }

            $baseTargets = array_map(
                static fn(float $native): float => $native * ($cap / $baseTotal),
                $baseNatives
            );
            $baseFinals = $this->allocator->allocate($baseTargets, $cap, $basePrecision);

            // Display chain runs independently — no conversion from base
            // (spec §8); it only scales when its own sum exceeds the cap.
            $displayFinals = null;
            $displayTotal = array_sum($displayNatives);
            if ($displayTotal > $cap) {
                $displayTargets = array_map(
                    static fn(float $native): float => $native * ($cap / $displayTotal),
                    $displayNatives
                );
                $displayFinals = $this->allocator->allocate($displayTargets, $cap, $displayPrecision);
            }

            foreach ($baseNatives as $itemKey => $baseNative) {
                $data = $itemEntries[$itemKey][$ruleId]->getDiscountData();
                $item = $itemByKey[$itemKey];

                $baseFinal = $baseFinals[$itemKey];
                $displayNative = $displayNatives[$itemKey];
                $displayFinal = $displayFinals !== null ? $displayFinals[$itemKey] : $displayNative;

                $baseRatio = $baseNative > 0.0 ? $baseFinal / $baseNative : 1.0;
                $displayRatio = ($displayFinals !== null && $displayNative > 0.0)
                    ? $displayFinal / $displayNative
                    : 1.0;

                // Mutate the breakdown entry in place (same entry objects the
                // address aggregation later reads).
                $data->setBaseAmount($baseFinal);
                $data->setAmount($displayFinal);
                $data->setBaseOriginalAmount((float) $data->getBaseOriginalAmount() * $baseRatio);
                $data->setOriginalAmount((float) $data->getOriginalAmount() * $displayRatio);

                // All deltas are <= 0: natives sit on the currency grid and the
                // allocator never allocates above the native amount (AC-7), so
                // the minFix invariant (item discount <= itemPrice x qty) holds.
                $baseDelta = $baseFinal - $baseNative;
                $displayDelta = $displayFinal - $displayNative;
                $item->setBaseDiscountAmount($item->getBaseDiscountAmount() + $baseDelta);
                $item->setDiscountAmount($item->getDiscountAmount() + $displayDelta);
                $deltas[] = ['item' => $item, 'base' => $baseDelta, 'display' => $displayDelta];
            }
        }

        if ($deltas === []) {
            return $this;
        }

        // Aggregate exactly like the native collector's final block, restricted
        // to the items of the current shipping assignment (native guards with
        // $itemsAggregate; identity check handles not-yet-saved items safely).
        $assignmentItems = new \SplObjectStorage();
        foreach ($shippingAssignment->getItems() as $assignmentItem) {
            $assignmentItems[$assignmentItem] = true;
        }
        $deltaDisplay = 0.0;
        $deltaBase = 0.0;
        foreach ($deltas as $delta) {
            if (!isset($assignmentItems[$delta['item']])) {
                continue;
            }
            $deltaDisplay += $delta['display'];
            $deltaBase += $delta['base'];
        }

        if ($deltaBase != 0.0 || $deltaDisplay != 0.0) {
            // Native aggregates item discounts as negative amounts
            // (aggregateItemDiscount); apply the reduction with the same sign.
            $total->addTotalAmount(self::TOTAL_CODE_DISCOUNT, -$deltaDisplay);
            $total->addBaseTotalAmount(self::TOTAL_CODE_DISCOUNT, -$deltaBase);
            $total->setSubtotalWithDiscount($total->getSubtotal() + $total->getDiscountAmount());
            $total->setBaseSubtotalWithDiscount(
                $total->getBaseSubtotal() + $total->getBaseDiscountAmount()
            );
            $address->setDiscountAmount($total->getDiscountAmount());
            $address->setBaseDiscountAmount($total->getBaseDiscountAmount());
            $address->setSubtotalWithDiscount($total->getSubtotal() + $total->getDiscountAmount());
            $address->setBaseSubtotalWithDiscount(
                $total->getBaseSubtotal() + $total->getBaseDiscountAmount()
            );

            // Address-level per-rule breakdown must reflect the capped amounts
            // (REST/GraphQL totals source) — rebuilt from the breakdowns of ALL
            // shipping-assignment items, mirroring the native aggregate set
            // ($itemsAggregate in Discount::collect): rules untouched by the cap
            // keep their entries, so capped + uncapped rules mix correctly.
            $this->rebuildAddressDiscounts($shippingAssignment->getItems(), $address);
        }

        return $this;
    }

    /**
     * Aggregate per-rule totals from the (now capped) item breakdowns back onto
     * the address extension attributes — mirrors the native per-rule
     * aggregation shape: RuleDiscount{discount, rule label, rule_id}. Parent
     * items are skipped like the native aggregate loop (children-calculated
     * breakdowns live on the parent item).
     *
     * @param array<int, \Magento\Quote\Model\Quote\Item\AbstractItem> $items
     * @param Address $address
     * @return void
     */
    private function rebuildAddressDiscounts(array $items, Address $address): void
    {
        $aggregates = [];
        foreach ($items as $item) {
            if ($item->getParentItem()) {
                continue;
            }
            $discounts = $item->getExtensionAttributes()
                ? $item->getExtensionAttributes()->getDiscounts()
                : null;
            if (!$discounts) {
                continue;
            }
            foreach ((array) $discounts as $entry) {
                $data = $entry->getDiscountData();
                if ($data === null) {
                    continue;
                }
                $ruleId = (int) $entry->getRuleID();
                if (!isset($aggregates[$ruleId])) {
                    $aggregates[$ruleId] = [
                        'amount' => 0.0,
                        'base_amount' => 0.0,
                        'original_amount' => 0.0,
                        'base_original_amount' => 0.0,
                        'rule_label' => (string) $entry->getRuleLabel(),
                    ];
                }
                $aggregates[$ruleId]['amount'] += (float) $data->getAmount();
                $aggregates[$ruleId]['base_amount'] += (float) $data->getBaseAmount();
                $aggregates[$ruleId]['original_amount'] += (float) $data->getOriginalAmount();
                $aggregates[$ruleId]['base_original_amount'] += (float) $data->getBaseOriginalAmount();
            }
        }

        $addressDiscounts = [];
        foreach ($aggregates as $ruleId => $aggregate) {
            $addressDiscounts[] = $this->ruleDiscountFactory->create(['data' => [
                'discount' => $this->discountDataFactory->create(['data' => [
                    'amount' => $aggregate['amount'],
                    'base_amount' => $aggregate['base_amount'],
                    'original_amount' => $aggregate['original_amount'],
                    'base_original_amount' => $aggregate['base_original_amount'],
                ]]),
                'rule' => $aggregate['rule_label'],
                'rule_id' => $ruleId,
            ]]);
        }

        if ($address->getExtensionAttributes() !== null) {
            $address->getExtensionAttributes()->setDiscounts($addressDiscounts);
        }
    }

    /**
     * Base-currency code for the base chain — the quote's persisted value
     * (set by Quote::beforeSave), store fallback for not-yet-saved quotes.
     *
     * @param Quote $quote
     * @return string
     */
    private function baseCurrencyCode(Quote $quote): string
    {
        return (string) ($quote->getBaseCurrencyCode()
            ?: $quote->getStore()->getBaseCurrencyCode());
    }

    /**
     * Quote (display) currency code for the display chain — the currency the
     * customer sees at checkout, persisted on the quote like the base code.
     *
     * @param Quote $quote
     * @return string
     */
    private function quoteCurrencyCode(Quote $quote): string
    {
        return (string) ($quote->getQuoteCurrencyCode()
            ?: $quote->getStore()->getCurrentCurrencyCode());
    }

    /**
     * Decimal precision of a currency from the locale price format (spec §8:
     * "theo currency precision — VND 0 decimals, không hard-code integer").
     * PriceCurrency::round() always rounds to 2, so the locale format is the
     * currency-aware source. The currency code is passed explicitly: the
     * format's no-code fallback resolves through the CURRENT request scope's
     * currency, which is wrong whenever the collect context (admin scope,
     * CLI, API) differs from the quote's store.
     *
     * @param string $currencyCode
     * @return int
     */
    private function currencyPrecision(string $currencyCode): int
    {
        $format = $this->localeFormat->getPriceFormat(null, $currencyCode);
        $precision = (int) ($format['precision'] ?? 2);
        return max(0, min(4, $precision));
    }
}
