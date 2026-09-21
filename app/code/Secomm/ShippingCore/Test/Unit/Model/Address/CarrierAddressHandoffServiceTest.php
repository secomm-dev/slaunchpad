<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Address;

use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CarrierAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\CarrierOperationAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Address\DestinationContextBuilderInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionManagerInterface;
use Secomm\VietNamAddress\Api\VnPrimaryCandidateSelectorInterface;
use Secomm\ShippingCore\Api\Failure\ShippingFailureReason;
use Secomm\ShippingCore\Model\Address\CarrierAddressHandoffService;
use Secomm\ShippingCore\Model\Address\Exception\UnsupportedDestinationException;
use Secomm\ShippingCore\Model\Address\ResolvedShippingAddress;
use Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface;

/**
 * TASK-T78YH6 — handoff matrix over a MOCKED resolution manager: delegation (single resolution
 * path), non-VN translation, EXACT/MAPPED/AMBIGUOUS/UNMAPPED semantics, fallback ALLOW-only.
 */
class CarrierAddressHandoffServiceTest extends TestCase
{
    private DestinationContextBuilderInterface&MockObject $contextBuilder;
    private ShippingAddressResolutionManagerInterface&MockObject $resolutionManager;
    private CarrierAddressHandoffService $service;
    private Address&MockObject $destination;

    private VnPrimaryCandidateSelectorInterface&MockObject $primaryCandidateSelector;

    protected function setUp(): void
    {
        $this->contextBuilder = $this->createMock(DestinationContextBuilderInterface::class);
        $this->resolutionManager = $this->createMock(ShippingAddressResolutionManagerInterface::class);
        $this->primaryCandidateSelector = $this->createMock(VnPrimaryCandidateSelectorInterface::class);
        $this->service = new CarrierAddressHandoffService($this->contextBuilder, $this->resolutionManager, $this->primaryCandidateSelector);
        $this->destination = $this->createMock(Address::class);
    }

    public function testDelegatesToResolutionManagerOnceWithBuiltContext(): void
    {
        $context = $this->createMock(ShippingAddressResolutionContextInterface::class);
        $this->contextBuilder->expects($this->once())->method('build')
            ->with($this->destination, $this->capability(supportsTextualFallback: false))
            ->willReturn($context);
        $this->resolutionManager->expects($this->once())->method('resolve')
            ->with($context, $this->callback(fn ($capability) => $capability instanceof CarrierAddressCapabilityInterface))
            ->willReturn($this->canonicalOutcome(VnAddressResolutionInterface::STATUS_MAPPED));

        $handoff = $this->service->handoff($this->destination, $this->capability(supportsTextualFallback: false));

        $this->assertTrue($handoff->isApplicable());
    }

    public function testNonVnDestinationIsTranslatedIntoNotApplicable(): void
    {
        // The ShippingCore-internal exception never reaches the carrier — carriers never catch it.
        $this->contextBuilder->method('build')
            ->willReturn($this->createMock(ShippingAddressResolutionContextInterface::class));
        $this->resolutionManager->expects($this->once())->method('resolve')
            ->willThrowException(new UnsupportedDestinationException(__('non-VN')));

        $handoff = $this->service->handoff($this->destination, $this->capability(supportsTextualFallback: false));

        $this->assertFalse($handoff->isApplicable());
        $this->assertNull($handoff->getResolvedAddress());
        $this->assertFalse($handoff->isTextualFallbackEligible());
        $this->assertSame(ShippingFailureReason::UNSUPPORTED_DESTINATION, $handoff->getFailureReason());
    }

    public function testExactResolvesWithResolvedAddressAndNoFallback(): void
    {
        $this->arrangeResolution(VnAddressResolutionInterface::STATUS_EXACT);

        $handoff = $this->service->handoff($this->destination, $this->capability(supportsTextualFallback: false));

        $this->assertTrue($handoff->isApplicable());
        $this->assertNotNull($handoff->getResolvedAddress());
        $this->assertSame(VnAddressResolutionInterface::STATUS_EXACT, $handoff->getResolvedAddress()?->getStatus());
        $this->assertFalse($handoff->isTextualFallbackEligible());
        $this->assertNull($handoff->getFailureReason());
    }

    public function testMappedResolvesWithResolvedAddressAndNoFallback(): void
    {
        $this->arrangeResolution(VnAddressResolutionInterface::STATUS_MAPPED);

        $handoff = $this->service->handoff($this->destination, $this->capability(supportsTextualFallback: false));

        $this->assertNotNull($handoff->getResolvedAddress());
        $this->assertSame(VnAddressResolutionInterface::STATUS_MAPPED, $handoff->getResolvedAddress()?->getStatus());
        $this->assertNull($handoff->getFailureReason());
    }

    public function testAmbiguousWithoutTextualCapabilityYieldsUnresolvedNotApplicableFallback(): void
    {
        $this->arrangeResolution(VnAddressResolutionInterface::STATUS_AMBIGUOUS);

        $handoff = $this->service->handoff($this->destination, $this->capability(supportsTextualFallback: false));

        $this->assertTrue($handoff->isApplicable());
        $this->assertNull($handoff->getResolvedAddress());
        $this->assertFalse($handoff->isTextualFallbackEligible());
        $this->assertSame(ShippingFailureReason::CANONICAL_UNRESOLVED, $handoff->getFailureReason());
    }

    public function testAmbiguousWithTextualCapabilityCommunicatesFallbackEligibility(): void
    {
        $this->arrangeResolution(VnAddressResolutionInterface::STATUS_AMBIGUOUS);

        $handoff = $this->service->handoff($this->destination, $this->capability(supportsTextualFallback: true));

        // ALLOWED only — the handoff never executes any textual fallback itself.
        $this->assertNull($handoff->getResolvedAddress());
        $this->assertTrue($handoff->isTextualFallbackEligible());
        $this->assertSame(ShippingFailureReason::CANONICAL_UNRESOLVED, $handoff->getFailureReason());
    }

    public function testUnmappedWithoutTextualCapabilityYieldsUnresolved(): void
    {
        $this->arrangeResolution(VnAddressResolutionInterface::STATUS_UNMAPPED);

        $handoff = $this->service->handoff($this->destination, $this->capability(supportsTextualFallback: false));

        $this->assertNull($handoff->getResolvedAddress());
        $this->assertFalse($handoff->isTextualFallbackEligible());
        $this->assertSame(ShippingFailureReason::CANONICAL_UNRESOLVED, $handoff->getFailureReason());
    }

    public function testUnmappedWithTextualCapabilityCommunicatesFallbackEligibility(): void
    {
        $this->arrangeResolution(VnAddressResolutionInterface::STATUS_UNMAPPED);

        $handoff = $this->service->handoff($this->destination, $this->capability(supportsTextualFallback: true));

        $this->assertNull($handoff->getResolvedAddress());
        $this->assertTrue($handoff->isTextualFallbackEligible());
        $this->assertSame(ShippingFailureReason::CANONICAL_UNRESOLVED, $handoff->getFailureReason());
    }

    /**
     * TASK-7AJ3K8 — prebuilt-context entry: non-quote address sources (RateRequest, sales
     * order address) resolve through the SAME manager path without faking a Quote\Address.
     */
    public function testHandoffContextBypassesTheQuoteBuilderAndResolvesDirectly(): void
    {
        $context = $this->createMock(ShippingAddressResolutionContextInterface::class);
        $capability = $this->capability(supportsTextualFallback: false);
        $this->contextBuilder->expects($this->never())->method('build');
        $this->resolutionManager->expects($this->once())->method('resolve')
            ->with($context, $capability)
            ->willReturn($this->canonicalOutcome(VnAddressResolutionInterface::STATUS_EXACT));

        $handoff = $this->service->handoffContext($context, $capability);

        $this->assertTrue($handoff->isApplicable());
        $this->assertNotNull($handoff->getResolvedAddress());
        $this->assertNull($handoff->getFailureReason());
    }

    public function testHandoffContextCarriesAmbiguousCandidatesNeverAPick(): void
    {
        $context = $this->createMock(ShippingAddressResolutionContextInterface::class);
        $capability = $this->capability(supportsTextualFallback: true);
        $this->resolutionManager->method('resolve')
            ->willReturn($this->canonicalOutcome(VnAddressResolutionInterface::STATUS_AMBIGUOUS));

        $handoff = $this->service->handoffContext($context, $capability);

        $this->assertNull($handoff->getResolvedAddress());
        $this->assertSame(['VNAP25-B2B2B2B2B2', 'VNAP25-A1A1A1A1A1'], $handoff->getCandidateCodes());
        $this->assertTrue($handoff->isTextualFallbackEligible());
    }

    // ---------- TASK-MD2BD3 (v10) — AddressResolutionPolicy on the context entry ----------

    private function operationCapability(bool $supportsTextualFallback): CarrierOperationAddressCapabilityInterface
    {
        return new class ($supportsTextualFallback) implements CarrierOperationAddressCapabilityInterface {
            public function __construct(private readonly bool $supported)
            {
            }

            public function getRequiredScheme(string $operation): string
            {
                return 'VN_ADMIN_PRE_2025';
            }

            public function supportsTextualFallback(string $operation): bool
            {
                return $this->supported;
            }

            public function getSupportedRepresentations(string $operation): array
            {
                return [\Secomm\ShippingCore\Api\Address\AddressRepresentation::UNIT_ID];
            }
        };
    }

    private function contextWithSource(): ShippingAddressResolutionContextInterface&MockObject
    {
        $context = $this->createMock(ShippingAddressResolutionContextInterface::class);
        $context->method('getSourceScheme')->willReturn('VN_ADMIN_2025');
        $context->method('getSourceUnitCode')->willReturn('VNA25-SOURCE01');
        $context->method('getTargetScheme')->willReturn('VN_ADMIN_PRE_2025');

        return $context;
    }

    private function selection(string $status, ?string $code): VnPrimaryCandidateSelectorInterface&MockObject
    {
        $selector = $this->createMock(VnPrimaryCandidateSelectorInterface::class);
        $selection = $this->createMock(\Secomm\VietNamAddress\Api\Data\VnPrimaryCandidateSelectionInterface::class);
        $selection->method('getStatus')->willReturn($status);
        $selection->method('getSelectedCode')->willReturn($code);
        $selector->method('selectPrimary')->willReturn($selection);

        return $selector;
    }

    public function testStrictPolicyRejectsAmbiguousWithoutCandidatesOrTextualFallback(): void
    {
        $service = new CarrierAddressHandoffService(
            $this->contextBuilder,
            $this->resolutionManager,
            $this->selection(\Secomm\VietNamAddress\Api\Data\VnPrimaryCandidateSelectionInterface::STATUS_NOT_APPLICABLE, null)
        );
        $context = $this->contextWithSource();
        $this->resolutionManager->method('resolve')
            ->willReturn($this->canonicalOutcome(VnAddressResolutionInterface::STATUS_AMBIGUOUS));
        $this->primaryCandidateSelector->expects($this->never())->method('selectPrimary');

        $handoff = $service->handoffContextForOperation(
            $context,
            $this->operationCapability(supportsTextualFallback: true),
            'RATE',
            \Secomm\ShippingCore\Api\Address\AddressResolutionPolicy::STRICT
        );

        $this->assertNull($handoff->getResolvedAddress());
        $this->assertSame(ShippingFailureReason::CANONICAL_UNRESOLVED, $handoff->getFailureReason());
        $this->assertSame([], $handoff->getCandidateCodes());
        $this->assertFalse($handoff->isTextualFallbackEligible());
    }

    public function testPickPrimarySelectsTheCuratedCandidateIntoAResolvedHandoff(): void
    {
        $selector = $this->selection(
            \Secomm\VietNamAddress\Api\Data\VnPrimaryCandidateSelectionInterface::STATUS_SELECTED,
            'VNAP25-SELECTED'
        );
        $service = new CarrierAddressHandoffService($this->contextBuilder, $this->resolutionManager, $selector);
        $context = $this->contextWithSource();
        $this->resolutionManager->method('resolve')
            ->willReturn($this->canonicalOutcome(VnAddressResolutionInterface::STATUS_AMBIGUOUS));
        $selector->expects($this->once())->method('selectPrimary')->with(
            'VN_ADMIN_2025',
            'VNA25-SOURCE01',
            'VN_ADMIN_PRE_2025',
            ['VNAP25-B2B2B2B2B2', 'VNAP25-A1A1A1A1A1']
        );

        $handoff = $service->handoffContextForOperation(
            $context,
            $this->operationCapability(supportsTextualFallback: true),
            'RATE',
            \Secomm\ShippingCore\Api\Address\AddressResolutionPolicy::PICK_PRIMARY
        );

        $this->assertNotNull($handoff->getResolvedAddress());
        $this->assertSame('VNAP25-SELECTED', $handoff->getResolvedAddress()->getUnitCode());
        $this->assertNull($handoff->getFailureReason());
        $this->assertSame([], $handoff->getCandidateCodes(), 'GHN/carriers never see candidate lists');
    }

    public function testPickPrimaryWithoutDesignatedPrimaryFailsClosedUnresolved(): void
    {
        $service = new CarrierAddressHandoffService(
            $this->contextBuilder,
            $this->resolutionManager,
            $this->selection(\Secomm\VietNamAddress\Api\Data\VnPrimaryCandidateSelectionInterface::STATUS_NO_DESIGNATED_PRIMARY, null)
        );
        $context = $this->contextWithSource();
        $this->resolutionManager->method('resolve')
            ->willReturn($this->canonicalOutcome(VnAddressResolutionInterface::STATUS_AMBIGUOUS));

        $handoff = $service->handoffContextForOperation(
            $context,
            $this->operationCapability(supportsTextualFallback: true),
            'RATE',
            \Secomm\ShippingCore\Api\Address\AddressResolutionPolicy::PICK_PRIMARY
        );

        $this->assertNull($handoff->getResolvedAddress());
        $this->assertSame(ShippingFailureReason::CANONICAL_UNRESOLVED, $handoff->getFailureReason());
        $this->assertSame([], $handoff->getCandidateCodes());
        $this->assertFalse($handoff->isTextualFallbackEligible());
    }

    public function testDefaultPolicyKeepsTheLegacyAmbiguousShape(): void
    {
        // BC lock: without an explicit policy the pre-v10 AMBIGUOUS shape is unchanged.
        $service = new CarrierAddressHandoffService(
            $this->contextBuilder,
            $this->resolutionManager,
            $this->createMock(VnPrimaryCandidateSelectorInterface::class)
        );
        $context = $this->contextWithSource();
        $this->resolutionManager->method('resolve')
            ->willReturn($this->canonicalOutcome(VnAddressResolutionInterface::STATUS_AMBIGUOUS));
        $capability = $this->operationCapability(supportsTextualFallback: true);

        $handoff = $service->handoffContextForOperation($context, $capability, 'RATE');

        $this->assertSame(
            ['VNAP25-B2B2B2B2B2', 'VNAP25-A1A1A1A1A1'],
            $handoff->getCandidateCodes()
        );
        $this->assertTrue($handoff->isTextualFallbackEligible());
        $this->assertSame(ShippingFailureReason::CANONICAL_UNRESOLVED, $handoff->getFailureReason());
    }
    private function arrangeResolution(string $status): void
    {
        $this->contextBuilder->method('build')
            ->willReturn($this->createMock(ShippingAddressResolutionContextInterface::class));
        $this->resolutionManager->method('resolve')
            ->willReturn($this->canonicalOutcome($status));
    }

    private function canonicalOutcome(string $status): ResolvedShippingAddress
    {
        return match ($status) {
            VnAddressResolutionInterface::STATUS_EXACT,
            VnAddressResolutionInterface::STATUS_MAPPED => new ResolvedShippingAddress(
                $status,
                'VN_ADMIN_PRE_2025',
                'VNAP25-9F8E7D6C5B'
            ),
            VnAddressResolutionInterface::STATUS_AMBIGUOUS => new ResolvedShippingAddress(
                $status,
                'VN_ADMIN_PRE_2025',
                null,
                ['VNAP25-B2B2B2B2B2', 'VNAP25-A1A1A1A1A1']
            ),
            default => new ResolvedShippingAddress(VnAddressResolutionInterface::STATUS_UNMAPPED, 'VN_ADMIN_PRE_2025', null),
        };
    }

    /**
     * Capability stub: textual-fallback explodes unless the service legitimately consults it —
     * which must only ever happen on the unresolved branch (the caller passes the expected value).
     */
    private function capability(bool $supportsTextualFallback): CarrierAddressCapabilityInterface
    {
        return new class ($supportsTextualFallback) implements CarrierAddressCapabilityInterface {
            public function __construct(private readonly bool $supported)
            {
            }

            public function getRequiredScheme(): string
            {
                return 'VN_ADMIN_PRE_2025';
            }

            public function supportsTextualFallback(): bool
            {
                return $this->supported;
            }
        };
    }
}
