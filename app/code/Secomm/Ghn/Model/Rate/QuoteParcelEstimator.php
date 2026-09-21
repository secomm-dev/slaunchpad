<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Rate;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Secomm\Ghn\Model\Exception\GhnRateEstimationException;
use Secomm\ShippingCore\Model\Physical\StoreWeightConverter;

/**
 * TASK-WAWNDS — quote-time GHN parcel estimator, lean first strategy
 * PRODUCT_UNIT_AS_PACKAGE: 1 sellable unit = 1 estimated package. Transient by design — the
 * estimate is never persisted and never becomes CREATE physical truth.
 *
 * Item expansion is Magento parent-parity (AbstractCarrierOnline::getAllItems, the same
 * expansion `processAdditionalValidation` runs today): virtual products and child items with a
 * parent are skipped; configurable/bundle parents either expand to their children
 * (ship-separately) or count as ONE solid unit (ship-together — Magento already resolves the
 * parent's weight to the child/dynamic value). This kills parent/child double counting by
 * construction (brief §21). Grouped products never reach this estimator as a parent — Magento
 * places the purchased simple items on the quote directly (brief §20).
 *
 * Failure taxonomy (brief §7/§23):
 *   - GHN_RATE_ESTIMATION_UNAVAILABLE — adapter/data limitation (no shippable units, decimal
 *     quantity that cannot be represented as whole packages, bundle-with-children packing
 *     ambiguity is NOT possible under the Magento expansion above so no invented packing);
 *   - GHN_RATE_INVALID_PARCEL_DATA — a shippable unit carries a non-positive weight: never
 *     treated as zero (brief §23/§24).
 *
 * Dimensions: NOT read at RATE (no Magento dimension-unit contract; sandbox-proven that the
 * Fee API prices type-5 from items[] weight alone, and that root dimensions materially change
 * a type-2 fee — sending unproven dimensions would distort pricing).
 */
class QuoteParcelEstimator
{
    public const REASON_ESTIMATION_UNAVAILABLE = 'GHN_RATE_ESTIMATION_UNAVAILABLE';
    public const REASON_INVALID_PARCEL_DATA = 'GHN_RATE_INVALID_PARCEL_DATA';

    private const SOURCE_QUOTE_ITEM_WEIGHT = 'quote_item_weight';

    public function __construct(
        private readonly StoreWeightConverter $weightConverter
    ) {
    }

    /**
     * @throws GhnRateEstimationException when a safe estimate is impossible (never a guess)
     */
    public function estimate(RateRequest $request): QuoteParcelEstimate
    {
        $storeId = $request->getStoreId() !== null ? (int) $request->getStoreId() : null;
        $packages = [];

        foreach ($this->expandItems($request) as $item) {
            $qty = (float) $item->getTotalQty();
            if ($qty <= 0.0) {
                throw new GhnRateEstimationException(
                    self::REASON_INVALID_PARCEL_DATA,
                    __('Quote item "%1" has a non-positive quantity.', (string) $item->getSku())
                );
            }
            if (floor($qty) !== $qty) {
                // PRODUCT_UNIT_AS_PACKAGE requires whole sellable units; a fractional unit is
                // NOT split into fractional packages (no invented packing, brief §22).
                throw new GhnRateEstimationException(
                    self::REASON_ESTIMATION_UNAVAILABLE,
                    __('Quote item "%1" has a fractional quantity that cannot map to whole packages.', (string) $item->getSku())
                );
            }

            $unitWeightKg = (float) $item->getWeight();
            if ($unitWeightKg <= 0.0) {
                // Missing weight is NEVER zero (brief §23): one shippable unit without a
                // trustworthy weight cannot be classified type 2 or 5.
                throw new GhnRateEstimationException(
                    self::REASON_INVALID_PARCEL_DATA,
                    __('Quote item "%1" has no usable unit weight.', (string) $item->getSku())
                );
            }

            $unitWeightGrams = $this->weightConverter->toGrams($unitWeightKg, $storeId);
            if ($unitWeightGrams <= 0.0) {
                throw new GhnRateEstimationException(
                    self::REASON_INVALID_PARCEL_DATA,
                    __('Quote item "%1" converts to a non-positive package weight.', (string) $item->getSku())
                );
            }

            $unitCount = (int) $qty;
            for ($unit = 1; $unit <= $unitCount; $unit++) {
                $packages[] = new EstimatedPackage(
                    (int) $item->getItemId(),
                    (string) $item->getSku(),
                    $unitWeightGrams,
                    self::SOURCE_QUOTE_ITEM_WEIGHT
                );
            }
        }

        if ($packages === []) {
            throw new GhnRateEstimationException(
                self::REASON_ESTIMATION_UNAVAILABLE,
                __('The quote contains no shippable units to estimate.')
            );
        }

        return new QuoteParcelEstimate($packages);
    }

    /**
     * Magento parent-parity item expansion (virtual skipped, parent/child de-duplicated,
     * ship-separately children expanded, ship-together composite = one solid unit).
     *
     * @return list<\Magento\Quote\Model\Quote\Item|mixed>
     */
    private function expandItems(RateRequest $request): array
    {
        $items = [];
        foreach ($request->getAllItems() ?: [] as $item) {
            $product = $item->getProduct();
            if ($product !== null && $product->isVirtual()) {
                continue;
            }
            if ($item->getParentItem()) {
                // Children are processed through (or already covered by) their parent line.
                continue;
            }

            if ($item->getHasChildren() && $item->isShipSeparately()) {
                foreach ($item->getChildren() as $child) {
                    $childProduct = $child->getProduct();
                    if (!$child->getFreeShipping() && ($childProduct === null || !$childProduct->isVirtual())) {
                        $items[] = $child;
                    }
                }
            } else {
                $items[] = $item;
            }
        }

        return $items;
    }
}
