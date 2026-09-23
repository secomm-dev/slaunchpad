<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — the Launchpad fallback price calculator (rework of
 * TASK-NQT782's provider onto the per-method composition model).
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Model;

use Launchpad\MageplazaTableRate\Model\Exception\FallbackConfigurationException;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Mageplaza\TableRateShipping\Model\Method;
use Mageplaza\TableRateShipping\Model\MethodFactory;
use Mageplaza\TableRateShipping\Model\ResourceModel\Rate\CollectionFactory as RateCollectionFactory;
use Mageplaza\TableRateShipping\Model\Source\CalculateRule;
use Secomm\ShippingCore\Api\Fallback\FallbackRateInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackRateProviderInterface;
use Secomm\ShippingCore\Api\Fallback\FallbackRateRequestInterface;
use Secomm\ShippingCore\Model\Fallback\FallbackRate;

/**
 * Implements the ShippingCore fallback provider contract against the Mageplaza TableRate
 * INTERNAL calculation engine (never the `Carrier\TableRate::collectRates()` checkout path —
 * the provider is a pricing source, not a Magento carrier invocation).
 *
 * Pipeline (TASK-NQT782 basis, reworked by TASK-5XQXZK): method_id → Method::isActive(store)
 * guard → Rate\Collection::filterByRequest → per-rate Rate::calculatePrice (Mageplaza's own
 * formula) → combined by the method's CalculateRule (SUM/MIN/MAX). The optional City/Area
 * narrowing runs inside the shared Rate\Collection plugin, so BOTH consumption paths (outer
 * coordinator with the real Magento RateRequest, and the service-level pool contract) use the
 * same matching semantics.
 *
 * Two entry points:
 *  - calculate(): composition entry — the outer fallback coordinator passes the REAL Magento
 *    RateRequest (destCity/destRegionId intact, so the city dimension works) and derives the
 *    cart scalars from package_* values.
 *  - getRate(): the ShippingCore FallbackRateProviderInterface contract — a service-level
 *    bucket code `mptr_<method_id>` maps onto the internal calculation, with scalars supplied
 *    by the provider-neutral request (no city dimension — documented limitation of the
 *    neutral contract until a neutral consumer requires it, directive §14).
 *
 * Known boundary (TASK-NQT782 SPEC §R2): Method::isActive() consults the ambient customer
 * session for group scoping — consistent on storefront checkout paths; encapsulated here,
 * never leaked into ShippingCore.
 *
 * The bridge calculates a fallback PRICE only — it never decides service eligibility, and it
 * never exposes method ids, Mageplaza models, or carrier codes through the result.
 */
class FallbackRateProvider implements FallbackRateProviderInterface
{
    /** Bucket-code convention for the service-level contract path: `mptr_<method_id>`. */
    public const BUCKET_CODE_PREFIX = 'mptr_';

    public function __construct(
        private readonly MethodFactory $methodFactory,
        private readonly RateCollectionFactory $rateCollectionFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getRate(
        string $serviceLevelCode,
        FallbackRateRequestInterface $request
    ): ?FallbackRateInterface {
        $methodId = $this->methodIdFromBucketCode($serviceLevelCode);
        if ($methodId === null) {
            // Not a Mageplaza bucket: plain absence, never an error (optional provider).
            return null;
        }

        $rateRequest = new RateRequest();
        $rateRequest->setDestCountryId($request->getCountryId());
        $rateRequest->setDestRegionId($request->getRegionId());
        $rateRequest->setDestPostcode($request->getPostcode());
        $rateRequest->setStoreId($request->getStoreId());
        $rateRequest->setAllItems([]);

        return $this->calculate(
            $methodId,
            $rateRequest,
            [
                'weight' => $request->getWeight(),
                'subtotal' => $request->getSubtotal(),
                'qty' => $request->getQty(),
            ]
        );
    }

    /**
     * Composition entry point — evaluates one Mageplaza method against the REAL Magento
     * RateRequest. Returns null when the method is inactive/out of scope for this store or
     * matches no configured row (no fallback rate — never a default zero). A method that does
     * not exist at all is a hard misconfiguration.
     */
    public function calculate(int $methodId, RateRequest $rateRequest, ?array $cartData = null): ?FallbackRateInterface
    {
        $method = $this->loadMethod($methodId);

        $storeId = (int) ($rateRequest->getStoreId() ?? 0);
        if (!$method->isActive($storeId)) {
            return null;
        }

        $cartData ??= $this->aggregateCartData($rateRequest);

        $rates = $this->rateCollectionFactory->create()
            ->addFieldToFilter('method_id', $methodId)
            ->filterByRequest($rateRequest, $cartData)
            ->getItems();

        if ($rates === []) {
            // No matching table-rate rule: no fallback rate — never a default zero (§16).
            return null;
        }

        return new FallbackRate(
            $this->combinePrices($rates, $method, $cartData),
            $this->buildLabel($method, $storeId)
        );
    }

    public static function methodIdFromBucketCode(string $serviceLevelCode): ?int
    {
        if (!str_starts_with($serviceLevelCode, self::BUCKET_CODE_PREFIX)) {
            return null;
        }

        $raw = substr($serviceLevelCode, strlen(self::BUCKET_CODE_PREFIX));

        return ctype_digit($raw) ? (int) $raw : null;
    }

    private function loadMethod(int $methodId): Method
    {
        $method = $this->methodFactory->create()->load($methodId);
        if (!$method->getId()) {
            throw new FallbackConfigurationException(
                new \Magento\Framework\Phrase(
                    'Fallback method #%1 does not exist — fix the fallback group configuration.',
                    [$methodId]
                )
            );
        }

        return $method;
    }

    /**
     * Cart scalars from the Magento request, mirroring Mageplaza's aggregate dimension subset
     * (weight/subtotal/qty — item-level volumetric behavior stays outside the documented
     * bridge subset).
     *
     * @return array{weight: float, subtotal: float, qty: float}
     */
    private function aggregateCartData(RateRequest $rateRequest): array
    {
        return [
            'weight' => (float) ($rateRequest->getPackageWeight() ?? 0),
            'subtotal' => (float) ($rateRequest->getPackageValue() ?? 0),
            'qty' => (float) ($rateRequest->getPackageQty() ?? 0),
        ];
    }

    /**
     * Customer-facing label = the Mageplaza method's own storefront title (per store when
     * configured, otherwise the internal name) — the group represents a business option, not
     * the extension.
     */
    private function buildLabel(Method $method, int $storeId): string
    {
        $title = trim((string) $method->getTitle($storeId));

        return $title !== '' ? $title : trim((string) $method->getName());
    }

    /**
     * Mageplaza's own per-rate price formula, combined by the method's configured rule.
     *
     * @param array<int, mixed> $rates
     * @param array{weight: float, subtotal: float, qty: float} $cartData
     */
    private function combinePrices(array $rates, Method $method, array $cartData): float
    {
        $result = null;
        foreach ($rates as $rate) {
            $price = (float) $rate->calculatePrice($cartData);
            if ($result === null) {
                $result = $price;

                continue;
            }

            $result = match ($method->getCalculateRule()) {
                CalculateRule::SUM => $result + $price,
                CalculateRule::MIN => min($result, $price),
                CalculateRule::MAX => max($result, $price),
                default => $result,
            };
        }

        return (float) $result;
    }
}
