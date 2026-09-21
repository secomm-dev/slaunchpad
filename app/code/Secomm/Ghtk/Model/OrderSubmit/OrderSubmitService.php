<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\OrderSubmit;

use Magento\Framework\Exception\LocalizedException;
use Magento\Shipping\Model\Shipment\Request;
use Secomm\Ghtk\Model\Address\GhtkAddressAdapter;
use Secomm\Ghtk\Model\Address\PickupAddressResolver;
use Secomm\Ghtk\Model\Config\GhtkConfig;
use Secomm\Ghtk\Model\Config\Source\WeightUnit;
use Secomm\Ghtk\Model\GhtkApiClient;
use Secomm\Ghtk\Model\GhtkApiException;
use Secomm\Ghtk\Model\Log\MaskingLogger;
use Secomm\Ghtk\Model\Origin\GhtkOriginProvider;
use Secomm\Ghtk\Model\Shipment\ShipmentWeightCalculator;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;
use Secomm\ShippingCore\Model\ShippingContextFactory;

/**
 * Orchestrates the Create-Shipping-Label submission to GHTK (SL-016 /
 * DEC-SL016-001). Runs INSIDE the native Magento label flow, before the
 * shipment is saved — any LocalizedException aborts the shipment creation
 * (native "no false success").
 *
 * Origin resolves through the SAME provider chain as the rate path
 * (DEC-SL015-001 §2); COD through CodAmountResolverInterface (never
 * grand_total); the submitted snapshot is persisted on the shipment comment.
 */
class OrderSubmitService
{
    public function __construct(
        private GhtkOriginProvider $originProvider,
        private PickupAddressResolver $pickupResolver,
        private GhtkAddressAdapter $destResolver,
        private CodAmountResolverInterface $codResolver,
        private OrderRequestMapper $requestMapper,
        private OrderResponseMapper $responseMapper,
        private GhtkApiClient $apiClient,
        private ShipmentWeightCalculator $weightCalculator,
        private ShippingContextFactory $contextFactory,
        private GhtkConfig $config,
        private MaskingLogger $logger
    ) {
    }

    /**
     * @throws LocalizedException Unusable pickup/destination, unsafe COD, or GHTK rejection.
     */
    public function submit(Request $request): OrderSubmitResult
    {
        $shipment = $request->getOrderShipment();
        $order = $shipment->getOrder();
        $storeId = $order->getStoreId() !== null ? (int) $order->getStoreId() : null;

        // Origin — same chain as the rate path.
        $context = $this->contextFactory->fromShipment($shipment, 'ghtk');
        $origin = $this->originProvider->resolve($context);
        $pickup = $this->pickupResolver->resolve($origin, ShippingAddressOperation::CREATE);
        if ($pickup === null) {
            throw new LocalizedException(
                __('GHTK pickup origin is not usable — set carriers/ghtk/pick_* or complete the Magento Shipping Origin.')
            );
        }

        // Destination — order shipping address through the same SL-008 machinery.
        $address = $order->getShippingAddress();
        $ward = $address !== null && $address->getCity() !== null ? trim((string) $address->getCity()) : '';
        if ($address === null || (string) $address->getCountryId() !== 'VN' || (int) $address->getRegionId() <= 0) {
            throw new LocalizedException(__('GHTK can only ship to complete Vietnam addresses.'));
        }
        $dest = $this->destResolver->resolve('VN', (int) $address->getRegionId(), null, $ward !== '' ? $ward : null, ShippingAddressOperation::CREATE);
        if ($dest === null) {
            throw new LocalizedException(__('GHTK could not resolve the shipping address (province/ward).'));
        }

        $weightGram = $this->resolveWeight($request, $shipment, $storeId);
        $codAmount = $this->codResolver->resolve($order, $shipment); // fail-fast on partial + COD
        $partnerOrderId = $this->buildPartnerOrderId($order);
        $products = $this->buildProducts($shipment, $storeId);

        $payload = $this->requestMapper->map(
            $request,
            $pickup,
            $dest,
            $weightGram,
            $codAmount,
            $partnerOrderId,
            $products,
            $this->config->getTransport($storeId)
        );

        try {
            $response = $this->apiClient->submitOrder($payload, $storeId);
        } catch (GhtkApiException $e) {
            // TASK-BE5YD2 — transport/business split by shared category; BOTH are a
            // single submission attempt (never an automatic POST retry). A technical
            // failure is deliberately "uncertain" (the provider may have created the
            // order): retrying with the SAME deterministic order.id is safe because
            // ORDER_ID_EXIST recovers the existing provider order.
            $technical = in_array(
                $e->getCategory(),
                [
                    \Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory::NETWORK,
                    \Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory::SERVER_ERROR,
                    \Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory::TIMEOUT,
                    \Secomm\ShippingCore\Api\Http\CarrierHttpErrorCategory::INVALID_RESPONSE,
                ],
                true
            );
            $this->logger->warning(
                'GHTK order submit failed.',
                ['order_id' => $partnerOrderId, 'classification' => $technical ? 'TECHNICAL' : 'BUSINESS', 'exception' => $e->getMessage()]
            );
            throw new LocalizedException(
                $technical
                    ? __('GHTK is temporarily unavailable (technical): %1. No result is known yet — retrying is safe: the same order reference will recover the existing GHTK order instead of creating a duplicate.', $e->getMessage())
                    : __('GHTK rejected the shipment submission: %1', $e->getMessage())
            );
        }

        $parsed = $this->responseMapper->parse($response);

        if ($parsed->getKind() === GhtkCreateResponse::KIND_MALFORMED) {
            $this->logger->warning(
                'GHTK order submit returned an unusable response.',
                ['order_id' => $partnerOrderId, 'classification' => 'TECHNICAL', 'detail' => $parsed->getMessage()]
            );
            throw new LocalizedException(
                __('GHTK returned an unusable response — no shipment was created. Retry the label creation; the deterministic order reference prevents duplicates.')
            );
        }

        if ($parsed->getKind() === GhtkCreateResponse::KIND_BUSINESS_REJECTION) {
            $this->logger->warning(
                'GHTK order submit rejected.',
                ['order_id' => $partnerOrderId, 'classification' => 'BUSINESS', 'error_code' => $parsed->getErrorCode(), 'reason' => $parsed->getMessage()]
            );
            throw new LocalizedException(__('GHTK rejected the order: %1', $parsed->getMessage() ?? 'unknown reason'));
        }

        if ($parsed->getKind() === GhtkCreateResponse::KIND_DUPLICATE_EXISTING) {
            $parsed = $this->validateDuplicate($parsed, $partnerOrderId);
        }

        $labelId = $parsed->getLabel();
        $tracking = $parsed->getTrackingNumber();
        if ($labelId === null || $tracking === null) {
            $this->logger->warning(
                'GHTK order succeeded without complete identity.',
                ['order_id' => $partnerOrderId, 'label' => $labelId, 'tracking' => $tracking]
            );
            throw new LocalizedException(
                __('GHTK accepted the order but returned no label/tracking — check the GHTK dashboard for partner order %1 before retrying.', $partnerOrderId)
            );
        }

        $recovered = $parsed->getKind() === GhtkCreateResponse::KIND_DUPLICATE_EXISTING;
        $result = new OrderSubmitResult(
            $partnerOrderId,
            $labelId,
            $tracking,
            $codAmount,
            $weightGram,
            $recovered,
            $parsed->getProviderStatus()
        );

        // Submitted-amount snapshot — later reads never re-derive COD from grand_total.
        $shipment->addComment(
            (string) __(
                $recovered
                    ? 'GHTK existing order recovered — partner: %1, label: %2, tracking: %3, pick_money: %4, weight (g): %5.'
                    : 'GHTK order submitted — partner: %1, label: %2, tracking: %3, pick_money: %4, weight (g): %5.',
                $partnerOrderId,
                $labelId,
                $tracking,
                number_format($codAmount, 0),
                $weightGram
            ),
            false,
            false
        );

        return $result;
    }

    /**
     * TASK-BE5YD2 / DEC-TASKBE5YD2-001 — ORDER_ID_EXIST is only recoverable when
     * the provider echoes OUR deterministic partner id AND still exposes a label
     * identity. Anything else is a hard conflict — never silently recovered.
     *
     * @throws LocalizedException on identity mismatch/absence (fail closed)
     */
    private function validateDuplicate(GhtkCreateResponse $parsed, string $expectedPartnerId): GhtkCreateResponse
    {
        if ($parsed->getPartnerId() === null) {
            $this->logger->warning(
                'GHTK duplicate order response carries no partner_id; recovery refused.',
                ['order_id' => $expectedPartnerId]
            );
            throw new LocalizedException(
                __('GHTK reports the order already exists but did not identify it — check the GHTK dashboard for partner order %1 before retrying.', $expectedPartnerId)
            );
        }

        if ($parsed->getPartnerId() !== $expectedPartnerId) {
            $this->logger->warning(
                'GHTK duplicate order response references a different partner id; recovery refused.',
                ['order_id' => $expectedPartnerId, 'provider_partner_id' => $parsed->getPartnerId()]
            );
            throw new LocalizedException(
                __('GHTK reports an existing order under a different reference (%1) — check the GHTK dashboard before retrying.', $parsed->getPartnerId())
            );
        }

        if (!$parsed->hasUsableIdentity()) {
            $this->logger->warning(
                'GHTK duplicate order response carries no label identity; recovery refused (runtime-verification note: exact ORDER_ID_EXIST payload shape pending TASK-44F7V7).',
                ['order_id' => $expectedPartnerId]
            );
            throw new LocalizedException(
                __('GHTK reports the existing order without a label identity — check the GHTK dashboard for partner order %1 before retrying.', $expectedPartnerId)
            );
        }

        return $parsed;
    }

    /**
     * Admin-entered package weights (native package popup, project weight
     * unit) win; otherwise fall back to product weights (DEC-022 contract).
     */
    private function resolveWeight(Request $request, \Magento\Sales\Model\Order\Shipment $shipment, ?int $storeId): int
    {
        $packages = $request->getPackages();
        $weight = 0.0;
        if (is_array($packages)) {
            foreach ($packages as $package) {
                $weight += (float) ($package['params']['weight'] ?? 0);
            }
        }

        if ($weight > 0) {
            $grams = $this->config->getWeightUnit($storeId) === WeightUnit::GRAM ? $weight : $weight * 1000;
            return (int) ceil(round(max(1.0, $grams), 6));
        }

        return $this->weightCalculator->calculateForShipment($shipment, $storeId);
    }

    /**
     * Deterministic WITHOUT the (not-yet-persisted) shipment id: the sequence
     * counts already-saved shipments, so a merchant retry after a failed
     * submit reuses the same partner id (DEC-SL016-001 §7).
     */
    private function buildPartnerOrderId(\Magento\Sales\Model\Order $order): string
    {
        $shipments = $order->getShipmentsCollection();
        $seq = $shipments !== null ? (int) $shipments->getSize() + 1 : 1;

        return 'ghtk-' . $order->getIncrementId() . '-' . $seq;
    }

    /**
     * @return array<int, array{name: string, weight: int, quantity: int, price: float}>
     * @throws LocalizedException When the shipment carries nothing shippable.
     */
    private function buildProducts(\Magento\Sales\Model\Order\Shipment $shipment, ?int $storeId): array
    {
        $unit = $this->config->getWeightUnit($storeId);
        $minWeight = $this->config->getMinWeight($storeId);
        $products = [];

        foreach ($shipment->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();
            if ($orderItem !== null && $orderItem->getIsVirtual()) {
                continue;
            }
            $type = $orderItem !== null ? (string) $orderItem->getProductType() : '';
            $hasParent = $orderItem !== null && $orderItem->getParentItem() !== null;
            if (!$hasParent && ($type === 'configurable' || $type === 'bundle')) {
                continue; // container parent; children carry qty/weight
            }

            // getProduct() lives on the ORDER item (not the shipment item).
            $product = $orderItem !== null ? $orderItem->getProduct() : null;
            $unitWeight = $product !== null ? (float) $product->getWeight() : 0.0;
            if ($unitWeight <= 0) {
                $unitWeight = $minWeight;
            }
            $gramsPerUnit = $unit === WeightUnit::GRAM ? $unitWeight : $unitWeight * 1000;

            $products[] = [
                'name' => (string) ($item->getName() !== null && $item->getName() !== ''
                    ? $item->getName()
                    : ($product?->getName() ?? 'Item')),
                'weight' => max(1, (int) ceil(round($gramsPerUnit, 6))),
                'quantity' => max(1, (int) $item->getQty()),
                'price' => (float) $item->getPrice(),
            ];
        }

        if ($products === []) {
            throw new LocalizedException(__('The shipment contains no shippable items.'));
        }

        return $products;
    }
}
