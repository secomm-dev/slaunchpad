<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Shipment;

use Magento\Sales\Model\Order\Shipment;
use Secomm\Ghn\Model\Config;
use Secomm\ShippingCore\Api\Physical\ShipmentPhysicalDataInterface;
use Secomm\ShippingCore\Model\Physical\PhysicalPackage;
use Secomm\ShippingCore\Model\Physical\ShipmentPhysicalData;
use Secomm\ShippingCore\Model\Physical\ShipmentPhysicalPersister;
use Secomm\ShippingCore\Model\Physical\StoreWeightConverter;
use Secomm\Ghn\Api\Client\GhnApiClientInterface;
use Secomm\Ghn\Api\Exception\ProviderAuthenticationException;
use Secomm\Ghn\Api\Exception\ProviderInvalidAddressException;
use Secomm\Ghn\Api\Exception\ProviderInvalidRequestException;
use Secomm\Ghn\Api\Exception\ProviderRateUnavailableException;
use Secomm\Ghn\Api\Exception\ProviderRemoteException;
use Secomm\Ghn\Api\Exception\ProviderServiceUnavailableException;
use Secomm\Ghn\Api\Exception\ProviderTimeoutException;
use Secomm\Ghn\Model\Address\Mapping\GhnMappingResolver;
use Secomm\Ghn\Model\Capability\GhnAddressCapability;
use Secomm\Ghn\Model\Capability\GhnCreateCapabilityAdapter;
use Secomm\Ghn\Model\Client\GhnEndpoints;
use Secomm\Ghn\Model\Exception\GhnMappingNotFoundException;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffServiceInterface;
use Secomm\ShippingCore\Api\Address\RuntimeAddressContextBuilderInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-9Q5ZAK (GHN-D) — THE GHN CREATE operation (per-operation contract v5: CREATE =
 * VN_ADMIN_2025 + TEXT_NAME → Stage-2 GHN_ADMIN_2025 verbatim names → Create Order →
 * {@see GhnCreateOutcome}).
 *
 * Stage 1 belongs to ShippingCore/VietNamAddress — consumed via the runtime context builder +
 * the PER-OPERATION handoff entry `handoffContextForOperation` (source==target 2025 → EXACT
 * passthrough; the canonical handoff carries Secomm identity ONLY — provider names start below
 * this line). Stage 2 is the APPROVED-mapping-only GhnMappingResolver: no fuzzy matching, no
 * candidate guessing, fail-closed.
 *
 * Money-safety rules (sandbox-proven, TASK-FMBBSD contract matrix):
 * - the POST is NEVER retried by this service — the client_order_code anchor row is written
 *   PENDING BEFORE the call, and reconciliation = resubmitting the SAME code;
 * - an uncertain transport result (timeout / 5xx / malformed 200) leaves the row UNKNOWN —
 *   never a blind second order;
 * - type 5 (>=20kg OR multi-package) ships as items[] = one entry per physical package
 *   (r2 physical-facts interpretation — DEC-TASK9Q5ZAK-001); per-package provider limits are
 *   fail-closed BEFORE the call, never clamped or split;
 * - COD: the collection amount is an upstream concern — GHN-D sends no cod_amount at all and
 *   never inspects the Magento payment method.
 */
class GhnShipmentCreationService
{
    private const OPERATION = 'create_order';

    /** Prefix of the shipment-scoped, retry-stable provider idempotency key (SPEC §19). */
    public const CLIENT_ORDER_CODE_PREFIX = 'GHNS';

    public function __construct(
        private readonly RuntimeAddressContextBuilderInterface $contextBuilder,
        private readonly CarrierAddressHandoffServiceInterface $handoffService,
        private readonly GhnAddressCapability $capability,
        private readonly GhnMappingResolver $mappingResolver,
        private readonly GhnApiClientInterface $apiClient,
        private readonly GhnCreateRequestBuilder $requestBuilder,
        private readonly GhnPhysicalParcelInterpreter $interpreter,
        private readonly ShipmentPhysicalPersister $physicalPersister,
        private readonly StoreWeightConverter $weightConverter,
        private readonly Config $config,
        private readonly GhnShipmentRepository $shipmentRepository,
        private readonly GhnLogger $logger
    ) {
    }

    /**
     * Create one GHN order for one persisted shipment. Never throws for domain failures —
     * the caller (observer / retry CLI) reads the outcome; unexpected \Throwable still
     * bubbles to the caller's own containment.
     *
     * @param array|null $postedRawPackages the admin package-information POST rows
     *        ([weight, lengthCm, widthCm, heightCm] — weight in the STORE weight unit) when the
     *        create originates from a fresh shipment save; null = read the persisted physical
     *        snapshot (retry/reconciliation path). No source → INVALID_PARCEL (fail-closed):
     *        physical facts are never invented, and merchant defaults are never silently used.
     */
    public function createForShipment(Shipment $shipment, ?array $postedRawPackages = null): GhnCreateOutcome
    {
        $shipmentId = (int) $shipment->getEntityId();
        $clientOrderCode = self::CLIENT_ORDER_CODE_PREFIX . $shipmentId;

        // Idempotency first: a SUBMITTED row means the provider order exists — never re-create.
        $existing = $this->shipmentRepository->findByShipmentId($shipmentId);
        if ($existing !== null
            && ($existing['provider_status'] ?? '') === GhnShipmentRepository::STATUS_SUBMITTED
            && !empty($existing['ghn_order_code'])
        ) {
            return GhnCreateOutcome::success(
                (string) $existing['client_order_code'],
                (string) $existing['ghn_order_code'],
                isset($existing['actual_fee']) ? (float) $existing['actual_fee'] : null,
                $existing['expected_delivery_at'] ?? null
            );
        }

        if ($existing === null) {
            // PENDING anchor BEFORE the provider call (SPEC §28 recovery design point).
            $this->shipmentRepository->insertPending([
                'shipping_reference' => $clientOrderCode,
                'magento_order_id' => (int) $shipment->getOrderId(),
                'magento_shipment_id' => $shipmentId,
                'shop_id' => '',
                'client_order_code' => $clientOrderCode,
                'cod_amount' => 0.0,
            ]);
        }

        try {
            return $this->resolveAndCreate($shipment, $clientOrderCode, $postedRawPackages);
        } catch (GhnMappingNotFoundException) {
            // Stage-2 domain: canonical unit exists, no APPROVED GHN mapping — never CANONICAL_UNRESOLVED.
            return $this->fail($clientOrderCode, GhnShipmentRepository::STATUS_FAILED, ShippingFailureReason::PROVIDER_MAPPING_MISSING);
        } catch (GhnCreateValidationException $validation) {
            return $this->fail($clientOrderCode, GhnShipmentRepository::STATUS_FAILED, $validation->getReasonToken());
        } catch (
            ProviderTimeoutException
            | ProviderRemoteException
            | ProviderServiceUnavailableException $technicalException
        ) {
            // Result uncertain — the order may exist provider-side; row stays UNKNOWN for
            // reconciliation (same client_order_code), never a blind second order.
            $this->logger->warning('GHN create technical failure', ['reason' => $technicalException->getMessage()]);

            return $this->fail($clientOrderCode, GhnShipmentRepository::STATUS_UNKNOWN, ShippingFailureReason::TECHNICAL_ERROR);
        } catch (
            ProviderAuthenticationException
            | ProviderInvalidAddressException
            | ProviderInvalidRequestException
            | ProviderRateUnavailableException $businessException
        ) {
            $this->logger->warning('GHN create unavailable', ['reason' => $businessException->getMessage()]);

            return $this->fail($clientOrderCode, GhnShipmentRepository::STATUS_FAILED, ShippingFailureReason::SERVICE_UNAVAILABLE);
        }
    }

    private function resolveAndCreate(Shipment $shipment, string $clientOrderCode, ?array $postedRawPackages): GhnCreateOutcome
    {
        $storeId = $shipment->getStoreId() !== null ? (int) $shipment->getStoreId() : null;
        $physical = $this->resolvePhysicalData($shipment, $postedRawPackages, $storeId);
        $plan = $this->interpreter->interpret($physical);

        $address = $shipment->getShippingAddress();
        $handoff = $this->handoffService->handoffContextForOperation(
            $this->contextBuilder->build(
                $address->getCountryId() !== null ? (string) $address->getCountryId() : null,
                (int) $address->getRegionId(),
                null,
                $address->getCity() !== null ? trim((string) $address->getCity()) ?: null : null,
                new GhnCreateCapabilityAdapter($this->capability)
            ),
            $this->capability,
            ShippingAddressOperation::CREATE
        );

        if (!$handoff->isApplicable()) {
            return $this->fail(
                $clientOrderCode,
                GhnShipmentRepository::STATUS_FAILED,
                $handoff->getFailureReason() ?? ShippingFailureReason::UNSUPPORTED_DESTINATION
            );
        }

        $resolved = $handoff->getResolvedAddress();
        if ($resolved === null) {
            return $this->fail(
                $clientOrderCode,
                GhnShipmentRepository::STATUS_FAILED,
                $handoff->getFailureReason() ?? ShippingFailureReason::CANONICAL_UNRESOLVED
            );
        }

        // CREATE is contractually the 2025 scheme (capability-required); a wrong-scheme handoff
        // is a clean mapping miss, never silently reinterpreted.
        $location = $this->mappingResolver->resolve(VnSchemes::VN_ADMIN_2025, (string) $resolved->getUnitCode());
        if (!$location->hasNewAddressNames()) {
            return $this->fail($clientOrderCode, GhnShipmentRepository::STATUS_FAILED, ShippingFailureReason::PROVIDER_MAPPING_MISSING);
        }

        $payload = $this->requestBuilder->build(
            $clientOrderCode,
            (string) $location->getProvinceName(),
            (string) $location->getWardName(),
            $plan,
            $address,
            $this->resolvePaymentTypeId($storeId),
            $this->resolveRequiredNote($storeId),
            $this->buildContent($shipment)
        );

        $response = $this->apiClient->post(self::OPERATION, GhnEndpoints::CREATE_ORDER, $payload);
        $orderCode = $response['order_code'] ?? null;
        if (!is_string($orderCode) || $orderCode === '') {
            // A 200 without a usable order code is an uncertain technical answer — never assume
            // success, never assume failure: the row stays UNKNOWN for reconciliation.
            throw new ProviderRemoteException(__('GHN %1 response has no order_code.', self::OPERATION));
        }

        $totalFee = isset($response['total_fee']) && is_numeric($response['total_fee'])
            ? (float) $response['total_fee']
            : null;
        $expectedDeliveryAt = isset($response['expected_delivery_time'])
            && is_string($response['expected_delivery_time'])
            ? $response['expected_delivery_time']
            : null;

        $this->shipmentRepository->markSubmitted(
            $clientOrderCode,
            $orderCode,
            $plan->getServiceTypeId(),
            $totalFee,
            $expectedDeliveryAt
        );
        $this->logger->call('GHN create submitted', [
            'client_order_code' => $clientOrderCode,
            'order_code' => $orderCode,
        ]);

        return GhnCreateOutcome::success($clientOrderCode, $orderCode, $totalFee, $expectedDeliveryAt);
    }

    /**
     * Persist the terminal non-success state and return the matching outcome.
     */
    private function fail(string $clientOrderCode, string $status, string $reasonToken): GhnCreateOutcome
    {
        $this->shipmentRepository->markNotSubmitted($clientOrderCode, $status, $reasonToken);

        return $status === GhnShipmentRepository::STATUS_UNKNOWN
            ? GhnCreateOutcome::technicalFailure($reasonToken, $clientOrderCode)
            : GhnCreateOutcome::unavailable($reasonToken, $clientOrderCode);
    }

    /**
     * Physical-facts source resolution (r2): (a) confirmed admin POST rows — the only
     * "authoritative" source, persisted as the shipment snapshot; (b) the persisted snapshot —
     * the retry/reconciliation path, guaranteed to replay the SAME parcel data; (c) nothing →
     * INVALID_PARCEL. Merchant defaults are never silently substituted here.
     *
     * @throws GhnCreateValidationException INVALID_PARCEL when no confirmed physical data exists
     * @throws \Magento\Framework\Exception\LocalizedException when the store weight unit is unusable
     */
    private function resolvePhysicalData(Shipment $shipment, ?array $postedRawPackages, ?int $storeId): ShipmentPhysicalDataInterface
    {
        if (is_array($postedRawPackages) && $postedRawPackages !== []) {
            $physical = $this->buildFromPostedRows($postedRawPackages, $storeId);
            $this->physicalPersister->persist($shipment, $physical);

            return $physical;
        }

        $snapshot = $this->physicalPersister->read($shipment);
        if ($snapshot !== null) {
            return $snapshot;
        }

        throw new GhnCreateValidationException(
            GhnCreateValidationException::REASON_INVALID_PARCEL,
            __('GHN create: no confirmed package weight/dimensions on the shipment. '
                . 'Enter the real package information when creating the shipment.')
        );
    }

    /**
     * @param array<int, array<string, mixed>> $postedRawPackages rows [weight, lengthCm, widthCm, heightCm]
     * @throws GhnCreateValidationException INVALID_PARCEL on unusable rows
     * @throws \Magento\Framework\Exception\LocalizedException on an unusable store weight unit
     */
    private function buildFromPostedRows(array $postedRawPackages, ?int $storeId): ShipmentPhysicalDataInterface
    {
        $packages = [];
        foreach (array_values($postedRawPackages) as $index => $row) {
            $weight = (float) ($row['weight'] ?? 0);
            $length = (int) ($row['length'] ?? 0);
            $width = (int) ($row['width'] ?? 0);
            $height = (int) ($row['height'] ?? 0);
            if ($weight <= 0 || $length <= 0 || $width <= 0 || $height <= 0) {
                throw new GhnCreateValidationException(
                    GhnCreateValidationException::REASON_INVALID_PARCEL,
                    __('GHN create: package #%1 has an empty weight or dimension.', (string) ($index + 1))
                );
            }
            $packages[] = new PhysicalPackage(
                (int) round($this->weightConverter->toGrams($weight, $storeId)),
                $length,
                $width,
                $height
            );
        }

        return ShipmentPhysicalData::fromPackages($packages);
    }

    private function resolvePaymentTypeId(?int $storeId): int
    {
        // The column default is 1 and the admin UI is a select; a tampered value surfaces as
        // INVALID_CONFIGURATION from the request builder.
        return $this->config->getPaymentType($storeId);
    }

    private function resolveRequiredNote(?int $storeId): string
    {
        return $this->config->getRequiredNote($storeId);
    }

    private function buildContent(Shipment $shipment): string
    {
        $names = [];
        foreach ($shipment->getItems() as $item) {
            $name = trim((string) $item->getName());
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return implode(', ', $names);
    }

}
