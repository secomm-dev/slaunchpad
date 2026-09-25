<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Shipment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Phrase;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Item;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\Ghn\Api\Client\GhnApiClientInterface;
use Secomm\Ghn\Api\Exception\ProviderAuthenticationException;
use Secomm\Ghn\Api\Exception\ProviderTimeoutException;
use Secomm\Ghn\Model\Address\Mapping\GhnLocation;
use Secomm\Ghn\Model\Address\Mapping\GhnMappingResolver;
use Secomm\Ghn\Model\Capability\GhnAddressCapability;
use Secomm\Ghn\Model\Capability\GhnCreateCapabilityAdapter;
use Secomm\Ghn\Model\Exception\GhnMappingNotFoundException;
use Secomm\Ghn\Model\Logger\GhnLogger;
use Secomm\Ghn\Model\Shipment\GhnCreateOutcome;
use Secomm\Ghn\Model\Shipment\GhnCreateRequestBuilder;
use Secomm\Ghn\Model\Shipment\GhnShipmentCreationService;
use Secomm\Ghn\Model\Shipment\GhnShipmentRepository;
use Secomm\Ghn\Model\Shipment\GhnPhysicalParcelInterpreter;
use Secomm\Ghn\Model\Shipment\GhnPhysicalLimit;
use Secomm\Cod\Api\CodCollectionLedgerInterface;
use Secomm\Cod\Api\CodCollectionResolverInterface;
use Secomm\Cod\Model\CodClaimConflictException;
use Secomm\Cod\Model\CodCollectionAttempt;
use Secomm\Cod\Model\CodCollectionDecision;
use Secomm\Ghn\Model\Config;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffServiceInterface;
use Secomm\ShippingCore\Api\Address\ResolvedShippingAddressInterface;
use Secomm\ShippingCore\Api\Address\RuntimeAddressContextBuilderInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Model\Address\CarrierAddressHandoff;
use Secomm\ShippingCore\Model\Physical\ShipmentPhysicalPersister;
use Secomm\ShippingCore\Model\Physical\StoreWeightConverter;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * TASK-9Q5ZAK r2 (DEC-TASK9Q5ZAK-001) — the CREATE operation contract: per-op CREATE handoff
 * (2025 target, TEXT_NAME), Stage-2 verbatim names, PENDING anchor before the POST, physical
 * facts sourced from the confirmed admin POST (fresh save) or the persisted snapshot (retry) —
 * never from merchant defaults — PROVIDER_MAPPING_MISSING ≠ CANONICAL_UNRESOLVED, and
 * business-vs-technical classification (UNKNOWN for uncertain results — never auto-retried).
 */
class GhnShipmentCreationServiceTest extends TestCase
{
    private RuntimeAddressContextBuilderInterface&MockObject $contextBuilder;

    private CarrierAddressHandoffServiceInterface&MockObject $handoffService;

    private GhnMappingResolver&MockObject $mappingResolver;

    private GhnApiClientInterface&MockObject $apiClient;

    private ScopeConfigInterface&MockObject $scopeConfig;

    private GhnShipmentRepository&MockObject $repository;

    private LoggerInterface&MockObject $psrLogger;

    private ShipmentPhysicalPersister&MockObject $physicalPersister;

    private Config&MockObject $config;

    /** @var array<int, string> execution order of the money-critical steps */
    private array $callOrder = [];

    /** Pre-existing persistence row (null = none). */
    private ?array $existingRow = null;

    private array $postResponse = ['order_code' => 'GHNSBX1', 'total_fee' => '68200', 'expected_delivery_time' => '2026-09-20T10:00:00Z'];

    /** @var array<int, array> payloads captured from the create POST */
    private array $postedPayloads = [];

    /** COD decision the mocked Secomm_Cod resolver returns (default: non-COD order). */
    private CodCollectionDecision $codDecision;

    private CodCollectionLedgerInterface&MockObject $ledger;

    /** @var array<int, string> ledger lifecycle calls in order */
    private array $ledgerCalls = [];

    private CodCollectionResolverInterface&MockObject $codResolver;

    private GhnShipmentCreationService $service;

    protected function setUp(): void
    {
        $this->contextBuilder = $this->createMock(RuntimeAddressContextBuilderInterface::class);
        $this->handoffService = $this->createMock(CarrierAddressHandoffServiceInterface::class);
        $this->mappingResolver = $this->createMock(GhnMappingResolver::class);
        $this->apiClient = $this->createMock(GhnApiClientInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->scopeConfig->method('getValue')->willReturn('kgs');
        $this->repository = $this->createMock(GhnShipmentRepository::class);
        $this->psrLogger = $this->createMock(LoggerInterface::class);
        $this->physicalPersister = $this->createMock(ShipmentPhysicalPersister::class);
        $this->physicalPersister->method('persist')->willReturnCallback(function (): void {
            $this->callOrder[] = 'persist';
        });
        $this->callOrder = [];
        $this->codDecision = CodCollectionDecision::notCod();
        $this->ledgerCalls = [];
        $this->ledger = $this->createMock(CodCollectionLedgerInterface::class);
        $this->ledger->method('recordPending')->willReturnCallback(function (CodCollectionAttempt $attempt, int $orderId, float $amount, string $currency): void {
            $this->callOrder[] = 'recordPending:' . (string) $amount;
            $this->ledgerCalls[] = 'recordPending:' . (string) $amount;
        });
        $this->ledger->method('markSubmitted')->willReturnCallback(function (CodCollectionAttempt $attempt, bool $recovered): void {
            $this->ledgerCalls[] = 'markSubmitted';
        });
        $this->ledger->method('markNotSubmitted')->willReturnCallback(function (CodCollectionAttempt $attempt, string $status, string $reason): void {
            $this->ledgerCalls[] = 'markNotSubmitted:' . $status . ':' . $reason;
        });
        $this->codResolver = $this->createMock(CodCollectionResolverInterface::class);
        $this->codResolver->method('resolve')->willReturnCallback(
            fn (): CodCollectionDecision => $this->codDecision
        );

        $this->config = $this->createMock(Config::class);
        $this->config->method('getPaymentType')->willReturn(1);
        $this->config->method('getRequiredNote')->willReturn('CHOXEMHANGKHONGTHU');

        $this->repository->method('findByShipmentId')->willReturnCallback(
            fn (): ?array => $this->existingRow
        );
        $this->repository->method('insertPending')->willReturnCallback(function (array $row): void {
            $this->callOrder[] = 'insertPending';
        });
        $this->apiClient->method('post')->willReturnCallback(function (string $operation, string $path, array $payload): array {
            $this->callOrder[] = 'post';
            $this->postedPayloads[] = $payload;

            return $this->postResponse;
        });

        $this->contextBuilder->method('build')->willReturn(
            $this->createMock(\Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface::class)
        );

        $this->service = new GhnShipmentCreationService(
            $this->contextBuilder,
            $this->handoffService,
            new GhnAddressCapability(),
            $this->mappingResolver,
            $this->apiClient,
            new GhnCreateRequestBuilder(),
            new GhnPhysicalParcelInterpreter(new GhnPhysicalLimit()),
            $this->physicalPersister,
            new StoreWeightConverter($this->scopeConfig),
            $this->config,
            $this->repository,
            $this->codResolver,
            $this->ledger,
            new GhnLogger($this->psrLogger)
        );
    }

    public function testPostedPackagesDriveTheCreateThroughThePerOperationHandoff(): void
    {
        $this->givenResolvedCurrentNames();
        $posted = [
            ['weight' => 1.5, 'length' => 30, 'width' => 20, 'height' => 10],
        ];
        $this->contextBuilder->expects($this->once())->method('build')->with(
            'VN',
            1224,
            null,
            'Long Vĩnh',
            $this->callback(fn ($capability): bool => $capability instanceof GhnCreateCapabilityAdapter
                && $capability->getRequiredScheme() === VnSchemes::VN_ADMIN_2025)
        );
        $this->handoffService->expects($this->once())->method('handoffContextForOperation')->with(
            $this->anything(),
            $this->isInstanceOf(GhnAddressCapability::class),
            ShippingAddressOperation::CREATE
        )->willReturn($this->handoff(applicable: true, unitCode: 'VNA25-F2118484F0'));
        $this->repository->expects($this->once())->method('insertPending')->with($this->callback(
            function (array $row): bool {
                return $row['client_order_code'] === 'GHNS42'
                    && $row['magento_shipment_id'] === 42
                    && $row['magento_order_id'] === 7;
            }
        ));
        $this->physicalPersister->expects($this->once())->method('persist')->with(
            $this->isInstanceOf(Shipment::class),
            $this->callback(fn ($physical): bool => $physical->getTotalWeightG() === 1500
                && count($physical->getPackages()) === 1)
        );

        $outcome = $this->service->createForShipment($this->shipment(), $posted);

        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame('GHNSBX1', $outcome->getOrderCode());
        $this->assertSame(['insertPending', 'persist', 'post'], $this->callOrder, 'anchor then snapshot then provider call');
    }

    public function testRetryReplaysThePersistedPhysicalSnapshot(): void
    {
        $this->givenResolvedCurrentNames();
        $this->physicalPersister->method('read')->willReturn(
            \Secomm\ShippingCore\Model\Physical\ShipmentPhysicalData::fromPackages([
                new \Secomm\ShippingCore\Model\Physical\PhysicalPackage(1500, 30, 20, 10),
            ])
        );
        $this->physicalPersister->expects($this->never())->method('persist');
        $this->apiClient->expects($this->once())->method('post')->with(
            'create_order',
            'v2/shipping-order/create',
            $this->callback(fn (array $payload): bool =>
                $payload['weight'] === 1500
                && $payload['length'] === 30
                && $payload['service_type_id'] === 2)
        );

        // null POST = retry/reconciliation path — the snapshot replays the same parcel data
        $outcome = $this->service->createForShipment($this->shipment(), null);

        $this->assertTrue($outcome->isSuccessful());
    }

    public function testSubmittedRowShortCircuitsWithoutAnyProviderCall(): void
    {
        $this->existingRow = [
            'provider_status' => 'SUBMITTED',
            'ghn_order_code' => 'GHNOLD1',
            'client_order_code' => 'GHNS42',
            'actual_fee' => '68200',
            'expected_delivery_at' => null,
        ];
        $this->apiClient->expects($this->never())->method('post');
        $this->repository->expects($this->never())->method('insertPending');

        $outcome = $this->service->createForShipment($this->shipment(), null);

        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame('GHNOLD1', $outcome->getOrderCode());
    }

    public function testNoConfirmedPhysicalDataFailsClosedWithoutProviderCall(): void
    {
        $this->physicalPersister->method('read')->willReturn(null);
        $this->apiClient->expects($this->never())->method('post');
        $this->repository->expects($this->once())->method('markNotSubmitted')->with(
            'GHNS42',
            'FAILED',
            'INVALID_PARCEL'
        );

        $outcome = $this->service->createForShipment($this->shipment(), null);

        $this->assertSame(GhnCreateOutcome::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertSame('INVALID_PARCEL', $outcome->getReason());
    }

    public function testMappingMissingIsProviderMappingMissingNeverCanonical(): void
    {
        $this->givenHandoff($this->handoff(applicable: true, unitCode: 'VNA25-F2118484F0'));
        $this->mappingResolver->method('resolve')->willThrowException(
            new GhnMappingNotFoundException(new Phrase('no approved mapping'))
        );
        $this->apiClient->expects($this->never())->method('post');
        $this->repository->expects($this->once())->method('markNotSubmitted')->with(
            'GHNS42',
            'FAILED',
            ShippingFailureReason::PROVIDER_MAPPING_MISSING
        );

        $outcome = $this->service->createForShipment($this->shipment(), $this->posted());

        $this->assertSame(GhnCreateOutcome::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertSame(ShippingFailureReason::PROVIDER_MAPPING_MISSING, $outcome->getReason());
    }

    public function testHeavySinglePackageNowShipsAsType5Items(): void
    {
        $this->postResponse = ['order_code' => 'GHNSHV1', 'total_fee' => '180000'];
        $this->givenResolvedCurrentNames();

        $outcome = $this->service->createForShipment(
            $this->shipment(totalWeight: 45.0),
            [['weight' => 45.0, 'length' => 60, 'width' => 50, 'height' => 40]]
        );

        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame('GHNSHV1', $outcome->getOrderCode());
        $payload = $this->postedPayloads[0];
        $this->assertSame(5, $payload['service_type_id']);
        $this->assertSame(45000, $payload['weight'], 'root weight (factual Σ) is provider-mandatory');
        foreach (['length', 'width', 'height'] as $rootField) {
            $this->assertArrayNotHasKey($rootField, $payload, "type-5 must not send root $rootField (sandbox-verified optional)");
        }
        $this->assertSame([
            ['name' => 'Package 1', 'quantity' => 1, 'weight' => 45000, 'length' => 60, 'width' => 50, 'height' => 40],
        ], $payload['items']);
    }

    public function testMultiPackageShipmentShipsType5WithOneItemPerPackageAndNoRootPhysicalFields(): void
    {
        $this->postResponse = ['order_code' => 'GHNSMP1', 'total_fee' => '250000'];
        $this->givenResolvedCurrentNames();

        // 30kg + 30kg = 60kg TOTAL — above the 50,000g per-PACKAGE limit is irrelevant: limits
        // are per package, and r3 sends NO synthetic root physical fields for type 5.
        $outcome = $this->service->createForShipment(
            $this->shipment(totalWeight: 50.0),
            [
                ['weight' => 30.0, 'length' => 50, 'width' => 40, 'height' => 30],
                ['weight' => 30.0, 'length' => 50, 'width' => 40, 'height' => 30],
            ]
        );

        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame('GHNSMP1', $outcome->getOrderCode());
        $payload = $this->postedPayloads[0];
        $this->assertSame(5, $payload['service_type_id']);
        $this->assertSame(60000, $payload['weight'], 'root weight (factual Σ) is provider-mandatory');
        foreach (['length', 'width', 'height'] as $rootField) {
            $this->assertArrayNotHasKey($rootField, $payload, "type-5 must not send root $rootField (sandbox-verified optional)");
        }
        $this->assertCount(2, $payload['items']);
        $this->assertSame(30000, $payload['items'][0]['weight']);
        $this->assertSame(30000, $payload['items'][1]['weight']);
    }

    public function testTechnicalFailureMarksRowUnknownForReconciliation(): void
    {
        $this->givenResolvedCurrentNames();
        $this->apiClient->method('post')->willThrowException(new ProviderTimeoutException(new Phrase('timed out')));
        $this->repository->expects($this->once())->method('markNotSubmitted')->with(
            'GHNS42',
            'UNKNOWN',
            ShippingFailureReason::TECHNICAL_ERROR
        );

        $outcome = $this->service->createForShipment($this->shipment(), $this->posted());

        $this->assertSame(GhnCreateOutcome::STATUS_TECHNICAL_FAILURE, $outcome->getStatus());
        $this->assertNull($outcome->getOrderCode(), 'an uncertain result never claims a provider order');
    }

    public function testAuthFailureIsBusinessUnavailableNeverTechnical(): void
    {
        $this->givenResolvedCurrentNames();
        $this->apiClient->method('post')->willThrowException(
            new ProviderAuthenticationException(new Phrase('token not configured'))
        );
        $this->repository->expects($this->once())->method('markNotSubmitted')->with(
            'GHNS42',
            'FAILED',
            ShippingFailureReason::SERVICE_UNAVAILABLE
        );

        $outcome = $this->service->createForShipment($this->shipment(), $this->posted());

        $this->assertSame(GhnCreateOutcome::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertSame(ShippingFailureReason::SERVICE_UNAVAILABLE, $outcome->getReason());
    }

    // ---------- helpers ----------

    public function testCodCollectibleDecisionFlowsIntoPayloadAndAnchor(): void
    {
        $this->givenResolvedCurrentNames();
        $this->codDecision = CodCollectionDecision::collectible(1250000.0, 'VND');

        $outcome = $this->service->createForShipment($this->shipment(), $this->posted());

        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame(1250000, $this->postedPayloads[0]['cod_amount'], 'payload carries the mapped decision');
    }

    public function testNonCodDecisionSendsZeroAndSkipsResolverOnlyOnce(): void
    {
        $this->givenResolvedCurrentNames();
        $this->codDecision = CodCollectionDecision::notCod();

        $outcome = $this->service->createForShipment($this->shipment(), $this->posted());

        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame(0, $this->postedPayloads[0]['cod_amount']);
    }

    public function testCodRejectionStopsBeforeAnchorRowAndPost(): void
    {
        $this->givenResolvedCurrentNames();
        $this->codDecision = CodCollectionDecision::rejected(
            CodCollectionDecision::REASON_COD_ALREADY_COLLECTED,
            'already collected elsewhere'
        );

        $outcome = $this->service->createForShipment($this->shipment(), $this->posted());

        $this->assertSame(GhnCreateOutcome::STATUS_COD_REJECTED, $outcome->getStatus());
        $this->assertSame(CodCollectionDecision::REASON_COD_ALREADY_COLLECTED, $outcome->getReason());
        $this->assertSame('already collected elsewhere', $outcome->getRejectionMessage());
        $this->assertNull($outcome->getOrderCode(), 'a rejected decision never submits anything');
        $this->assertNotContains('insertPending', $this->callOrder, 'no anchor row is written for a rejection');
        $this->assertNotContains('post', $this->callOrder, 'no POST for a rejection');
    }

    public function testCodAmountAboveProviderCapIsUnavailableBeforeAnyWrite(): void
    {
        $this->givenResolvedCurrentNames();
        $this->codDecision = CodCollectionDecision::collectible(50000001.0, 'VND');

        $outcome = $this->service->createForShipment($this->shipment(), $this->posted());

        $this->assertSame(GhnCreateOutcome::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertSame('COD_AMOUNT_EXCEEDS_PROVIDER_LIMIT', $outcome->getReason());
        $this->assertNotContains('insertPending', $this->callOrder, 'no anchor row above the provider cap');
        $this->assertNotContains('post', $this->callOrder);
    }

    public function testNonVndCollectibleDecisionIsCodRejectedBeforeAnyWrite(): void
    {
        // DEC-TASKDFGFZ9-004: currency SUPPORT is the carrier's concern — GHN rejects a
        // non-VND COD order before the ledger is armed, the anchor written or the POST.
        $this->givenResolvedCurrentNames();
        $this->codDecision = CodCollectionDecision::collectible(99.9, 'USD');

        $outcome = $this->service->createForShipment($this->shipment(), $this->posted());

        $this->assertSame(GhnCreateOutcome::STATUS_COD_REJECTED, $outcome->getStatus());
        $this->assertSame(CodCollectionDecision::REASON_CURRENCY_UNSUPPORTED, $outcome->getReason());
        $this->assertStringContainsString('USD', (string) $outcome->getRejectionMessage());
        $this->assertNotContains('insertPending', $this->callOrder);
        $this->assertNotContains('post', $this->callOrder);
        $this->assertSame([], $this->ledgerCalls);
    }

    public function testZeroAmountCodDecisionShipsWithZeroAndNeverClaimsTheLedger(): void
    {
        // DEC-TASKDFGFZ9-004: zero-total COD order STAYS COD — cod_amount 0 on the wire;
        // nothing to collect → no ledger claim.
        $this->givenResolvedCurrentNames();
        $this->codDecision = CodCollectionDecision::collectible(0.0, 'VND');

        $outcome = $this->service->createForShipment($this->shipment(), $this->posted());

        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame(0, $this->postedPayloads[0]['cod_amount']);
        $this->assertSame([], $this->ledgerCalls);
    }

    public function testClaimConflictBecomesCodRejectedWithoutAnchorOrPost(): void
    {
        // The engine-enforced one-collection claim lives in Secomm_Cod — a losing attempt
        // surfaces COD_REJECTED (admin-visible comment via the observer), never a POST.
        $this->givenResolvedCurrentNames();
        $this->codDecision = CodCollectionDecision::collectible(1250000.0, 'VND');
        $this->ledger->method('recordPending')->willReturnCallback(
            function (CodCollectionAttempt $attempt, int $orderId, float $amount, string $currency): void {
                throw new CodClaimConflictException(
                    __('Order #7 already has an active COD collection claim (held by ghtk shipment "ghtk-100000001-1") — P1 collects COD once per order.')
                );
            }
        );

        $outcome = $this->service->createForShipment($this->shipment(), $this->posted());

        $this->assertSame(GhnCreateOutcome::STATUS_COD_REJECTED, $outcome->getStatus());
        $this->assertSame(CodCollectionDecision::REASON_COD_ALREADY_COLLECTED, $outcome->getReason());
        $this->assertStringContainsString('ghtk-100000001-1', (string) $outcome->getRejectionMessage());
        $this->assertNotContains('insertPending', $this->callOrder);
        $this->assertNotContains('post', $this->callOrder);
    }

    public function testPrePostFailuresReleaseTheLedgerClaimAsFailed(): void
    {
        // Recovery fix: a handoff failure happens BEFORE the POST — the attempt definitively
        // never collected → the ledger claim must be RELEASED (FAILED), never left PENDING.
        $this->codDecision = CodCollectionDecision::collectible(1250000.0, 'VND');
        $notApplicable = new CarrierAddressHandoff(
            applicable: false,
            resolvedAddress: null,
            textualFallbackEligible: false,
            failureReason: ShippingFailureReason::UNSUPPORTED_DESTINATION
        );
        $this->givenHandoff($notApplicable);

        $outcome = $this->service->createForShipment($this->shipment(), $this->posted());

        $this->assertSame(GhnCreateOutcome::STATUS_UNAVAILABLE, $outcome->getStatus());
        $this->assertContains('markNotSubmitted:FAILED:' . ShippingFailureReason::UNSUPPORTED_DESTINATION, $this->ledgerCalls);
    }

    public function testLedgerArmedBeforeAnchorAndPostThenMirrorsSuccess(): void
    {
        $this->givenResolvedCurrentNames();
        $this->codDecision = CodCollectionDecision::collectible(1250000.0, 'VND');

        $outcome = $this->service->createForShipment($this->shipment(), $this->posted());

        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame(
            ['recordPending:1250000', 'insertPending', 'persist', 'post'],
            $this->callOrder,
            'ledger armed BEFORE the anchor insert and the POST'
        );
        $this->assertSame(['recordPending:1250000', 'markSubmitted'], $this->ledgerCalls, 'success mirrors onto the ledger');
    }

    public function testNonCodDecisionNeverTouchesTheLedger(): void
    {
        $this->givenResolvedCurrentNames();
        $this->codDecision = CodCollectionDecision::notCod();

        $outcome = $this->service->createForShipment($this->shipment(), $this->posted());

        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame([], $this->ledgerCalls, 'non-COD orders never enter the ledger');
    }

    public function testRejectedDecisionNeverArmsTheLedger(): void
    {
        $this->givenResolvedCurrentNames();
        $this->codDecision = CodCollectionDecision::rejected(
            CodCollectionDecision::REASON_COD_ALREADY_COLLECTED,
            'already collected elsewhere'
        );

        $this->service->createForShipment($this->shipment(), $this->posted());

        $this->assertSame([], $this->ledgerCalls, 'a rejected attempt never arms the ledger');
    }

    public function testCodAmountAboveCapNeverArmsTheLedger(): void
    {
        $this->givenResolvedCurrentNames();
        $this->codDecision = CodCollectionDecision::collectible(50000001.0, 'VND');

        $this->service->createForShipment($this->shipment(), $this->posted());

        $this->assertSame([], $this->ledgerCalls, 'above-cap amounts never arm the ledger');
    }

    public function testTechnicalFailureMirrorsUnknownOntoLedger(): void
    {
        $this->givenResolvedCurrentNames();
        $this->codDecision = CodCollectionDecision::collectible(1250000.0, 'VND');
        $this->apiClient->method('post')->willReturnCallback(
            function (string $operation, string $path, array $payload): array {
                $this->callOrder[] = 'post';
                $this->postedPayloads[] = $payload;
                throw new ProviderTimeoutException(__('GHN timeout'));
            }
        );

        $outcome = $this->service->createForShipment($this->shipment(), $this->posted());

        $this->assertSame(GhnCreateOutcome::STATUS_TECHNICAL_FAILURE, $outcome->getStatus());
        $this->assertContains('markNotSubmitted:UNKNOWN:TECHNICAL_ERROR', $this->ledgerCalls);
    }

    private function givenResolvedCurrentNames(): void
    {
        $this->givenHandoff($this->handoff(applicable: true, unitCode: 'VNA25-F2118484F0'));
        $this->mappingResolver->method('resolve')->willReturn(
            new GhnLocation('VN_ADMIN_2025', 'VNA25-F2118484F0', null, null, null, 'Vũng Tàu', 'Xã Long Vĩnh')
        );
    }

    private function givenHandoff(CarrierAddressHandoffInterface $handoff): void
    {
        $this->handoffService->method('handoffContextForOperation')->willReturn($handoff);
    }

    private function handoff(bool $applicable, ?string $unitCode = null): CarrierAddressHandoff
    {
        $resolved = null;
        if ($applicable && $unitCode !== null) {
            $resolved = $this->createMock(ResolvedShippingAddressInterface::class);
            $resolved->method('isResolved')->willReturn(true);
            $resolved->method('getSchemeCode')->willReturn(VnSchemes::VN_ADMIN_2025);
            $resolved->method('getUnitCode')->willReturn($unitCode);
        }

        return new CarrierAddressHandoff(
            applicable: $applicable,
            resolvedAddress: $resolved,
            textualFallbackEligible: false,
            failureReason: $resolved === null && $applicable ? ShippingFailureReason::CANONICAL_UNRESOLVED : null
        );
    }

    /**
     * The confirmed admin rows used across happy-path tests.
     *
     * @return array<int, array<string, float|int>>
     */
    private function posted(): array
    {
        return [['weight' => 1.5, 'length' => 30, 'width' => 20, 'height' => 10]];
    }

    private function rebuildService(): GhnShipmentCreationService
    {
        return new GhnShipmentCreationService(
            $this->contextBuilder,
            $this->handoffService,
            new GhnAddressCapability(),
            $this->mappingResolver,
            $this->apiClient,
            new GhnCreateRequestBuilder(),
            new GhnPhysicalParcelInterpreter(new GhnPhysicalLimit()),
            $this->physicalPersister,
            new StoreWeightConverter($this->scopeConfig),
            $this->config,
            $this->repository,
            $this->codResolver,
            $this->ledger,
            new GhnLogger($this->psrLogger)
        );
    }

    private function shipment(?float $totalWeight = null): Shipment&MockObject
    {
        $order = $this->createMock(\Magento\Sales\Model\Order::class);
        $order->method('getOrderCurrencyCode')->willReturn('VND');
        $order->method('getIncrementId')->willReturn('100000007');

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getEntityId')->willReturn(42);
        $shipment->method('getOrderId')->willReturn(7);
        $shipment->method('getStoreId')->willReturn(1);
        $shipment->method('getTotalWeight')->willReturn($totalWeight);
        $shipment->method('getOrder')->willReturn($order);

        $item = $this->createMock(Item::class);
        $item->method('getWeight')->willReturn($totalWeight ?? 1.5);
        $item->method('getQty')->willReturn(1.0);
        $item->method('getName')->willReturn('Waffle Blanket');
        $shipment->method('getItems')->willReturn([$item]);

        $address = $this->createMock(OrderAddressInterface::class);
        $address->method('getCountryId')->willReturn('VN');
        $address->method('getRegionId')->willReturn(1224);
        $address->method('getCity')->willReturn('Long Vĩnh');
        $address->method('getFirstname')->willReturn('An');
        $address->method('getLastname')->willReturn('Nguyễn');
        $address->method('getTelephone')->willReturn('0901234567');
        $address->method('getStreet')->willReturn(['12 Nguyễn Huệ']);
        $shipment->method('getShippingAddress')->willReturn($address);

        return $shipment;
    }
}
