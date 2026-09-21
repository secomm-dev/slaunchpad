<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Address;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\AddressRepresentation;
use Secomm\ShippingCore\Api\Address\CarrierOperationAddressCapabilityInterface;
use Secomm\ShippingCore\Api\Address\ResolvedShippingAddressInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionManagerInterface;
use Secomm\ShippingCore\Model\Address\CarrierAddressHandoffService;
use Secomm\ShippingCore\Model\Address\DestinationContextBuilder;
use Secomm\ShippingCore\Model\Address\ResolvedShippingAddress;
use Secomm\ShippingCore\Model\Address\RuntimeAddressContextBuilder;
use Secomm\VietNamAddress\Api\Data\VnOperationalResolutionInterface;
use Secomm\VietNamAddress\Api\VnOperationalAddressResolverInterface;
use Secomm\VietNamAddress\Api\VnOperationalNameResolverInterface;
use Secomm\VietNamAddress\Api\Data\VnAddressResolutionInterface;

/**
 * TASK-Y3X6H5 — PER-OPERATION capability through the handoff service (Delta A): RATE and
 * CREATE may require different schemes/representations; ShippingCore owns only the scheme and
 * the representation CATEGORY (test-defined codes — no GHN provider ids anywhere).
 */
class OperationAddressCapabilityTest extends TestCase
{
    private ShippingAddressResolutionManagerInterface&MockObject $resolutionManager;
    private CarrierOperationAddressCapabilityInterface&MockObject $capability;
    private ResolvedShippingAddressInterface&MockObject $resolved;
    private CarrierAddressHandoffService $service;

    /** Target schemes the resolution manager was invoked with, in call order. */
    private array $capturedTargetSchemes = [];

    protected function setUp(): void
    {
        // Real E-C0 builder + REAL runtime context builder (pure translators) so the per-op
        // capability flows through the exact production context-building path.
        $runtimeBuilder = new RuntimeAddressContextBuilder(
            $this->createMock(VnOperationalAddressResolverInterface::class),
            $this->createMock(VnOperationalNameResolverInterface::class)
        );
        // The builder resolves runtime identity with city_id > 0 — return an unresolved bridge
        // result (identity null): the context carries no source identity, target scheme still set.
        $this->resolutionManager = $this->createMock(ShippingAddressResolutionManagerInterface::class);
        $this->resolutionManager->method('resolve')->willReturnCallback(
            function (ShippingAddressResolutionContextInterface $context): ResolvedShippingAddressInterface {
                $this->capturedTargetSchemes[] = $context->getTargetScheme();

                return $this->resolved;
            }
        );
        $this->service = new CarrierAddressHandoffService(
            new DestinationContextBuilder($runtimeBuilder),
            $this->resolutionManager,
            $this->createMock(\Secomm\VietNamAddress\Api\VnPrimaryCandidateSelectorInterface::class)
        );
        $this->capability = $this->createMock(CarrierOperationAddressCapabilityInterface::class);
        $this->capability->method('getRequiredScheme')->willReturnCallback(
            fn (string $operation): string => $operation === ShippingAddressOperation::RATE
                ? 'VN_ADMIN_PRE_2025'
                : 'VN_ADMIN_2025'
        );
        $this->capability->method('getSupportedRepresentations')->willReturnCallback(
            fn (string $operation): array => $operation === ShippingAddressOperation::RATE
                ? [AddressRepresentation::UNIT_ID]
                : [AddressRepresentation::TEXT_NAME]
        );
        $this->capability->method('supportsTextualFallback')->willReturnCallback(
            fn (string $operation): bool => $operation === ShippingAddressOperation::RATE
        );
        $this->resolved = $this->createMock(ResolvedShippingAddressInterface::class);
        $this->resolved->method('isResolved')->willReturn(true);
        $this->resolved->method('getCandidateCodes')->willReturn([]);
    }

    public function testRateAndCreateMayRequireDifferentSchemes(): void
    {
        $this->service->handoffForOperation($this->destination(), $this->capability, ShippingAddressOperation::RATE);
        $this->service->handoffForOperation($this->destination(), $this->capability, ShippingAddressOperation::CREATE);

        $this->assertSame(
            ['VN_ADMIN_PRE_2025', 'VN_ADMIN_2025'],
            $this->capturedTargetSchemes,
            'RATE and CREATE must target different schemes per the per-operation capability.'
        );
    }

    public function testRepresentationsFollowTheOperation(): void
    {
        $rateHandoff = $this->service->handoffForOperation($this->destination(), $this->capability, ShippingAddressOperation::RATE);
        $createHandoff = $this->service->handoffForOperation($this->destination(), $this->capability, ShippingAddressOperation::CREATE);

        $this->assertSame([AddressRepresentation::UNIT_ID], $rateHandoff->getSupportedRepresentations());
        $this->assertSame([AddressRepresentation::TEXT_NAME], $createHandoff->getSupportedRepresentations());
    }

    public function testTextualFallbackEligibilityFollowsTheOperation(): void
    {
        // AMBIGUOUS unresolved result (never auto-picked): eligibility comes per operation.
        $this->resolutionManager = $this->createMock(ShippingAddressResolutionManagerInterface::class);
        $this->resolutionManager->method('resolve')->willReturn(
            new ResolvedShippingAddress(
                VnAddressResolutionInterface::STATUS_AMBIGUOUS,
                'VN_ADMIN_PRE_2025',
                null,
                ['VNAP25-A1A1A1A1A1', 'VNAP25-B2B2B2B2B2']
            )
        );
        $service = new CarrierAddressHandoffService(
            new DestinationContextBuilder($this->runtimeBuilder()),
            $this->resolutionManager,
            $this->createMock(\Secomm\VietNamAddress\Api\VnPrimaryCandidateSelectorInterface::class)
        );

        $rateHandoff = $service->handoffForOperation($this->destination(), $this->capability, ShippingAddressOperation::RATE);
        $createHandoff = $service->handoffForOperation($this->destination(), $this->capability, ShippingAddressOperation::CREATE);

        $this->assertTrue($rateHandoff->isTextualFallbackEligible());
        $this->assertFalse($createHandoff->isTextualFallbackEligible());
        $this->assertNull($rateHandoff->getResolvedAddress());
    }

    public function testUnknownOperationIsAConfigurationError(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown shipping address operation');
        $this->service->handoffForOperation($this->destination(), $this->capability, 'CANCEL');
    }

    public function testContextSchemeMismatchFailsFast(): void
    {
        $context = $this->createMock(ShippingAddressResolutionContextInterface::class);
        $context->method('getTargetScheme')->willReturn('VN_ADMIN_2025');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('requires scheme');
        $this->service->handoffContextForOperation(
            $context,
            $this->capability,
            ShippingAddressOperation::RATE
        );
    }

    public function testLegacyHandoffBehaviorUnchanged(): void
    {
        // Regression: the deprecated per-carrier path keeps its exact pre-Y3X6H5 semantics.
        $capability = $this->createMock(\Secomm\ShippingCore\Api\Address\CarrierAddressCapabilityInterface::class);
        $capability->method('getRequiredScheme')->willReturn('VN_ADMIN_PRE_2025');
        $capability->method('supportsTextualFallback')->willReturn(true);

        $this->resolutionManager = $this->createMock(ShippingAddressResolutionManagerInterface::class);
        $this->resolutionManager->method('resolve')->willReturn($this->resolved);
        $service = new CarrierAddressHandoffService(
            new DestinationContextBuilder($this->runtimeBuilder()),
            $this->resolutionManager,
            $this->createMock(\Secomm\VietNamAddress\Api\VnPrimaryCandidateSelectorInterface::class)
        );

        $handoff = $service->handoff($this->destination(), $capability);

        $this->assertTrue($handoff->isApplicable());
        $this->assertSame([], $handoff->getSupportedRepresentations(), 'Legacy path exposes no per-op representations.');
    }

    private function runtimeBuilder(): RuntimeAddressContextBuilder
    {
        $resolver = $this->createMock(VnOperationalAddressResolverInterface::class);
        $resolver->method('resolveFromRuntime')->willReturn(
            $this->createMock(VnOperationalResolutionInterface::class)
        );

        return new RuntimeAddressContextBuilder(
            $resolver,
            $this->createMock(VnOperationalNameResolverInterface::class)
        );
    }

    private function destination(): Address
    {
        // Bare Magento Quote\Address cannot be constructed in unit tests — a typed mock with
        // the scalar destination fields stands in (same pattern as DestinationContextBuilderTest).
        $destination = $this->createMock(Address::class);
        $destination->method('getCountryId')->willReturn('VN');
        $destination->method('getRegionId')->willReturn('521');
        $destination->method('getData')->with('city_id')->willReturn(12345);
        $destination->method('getStreet')->willReturn([]);

        return $destination;
    }
}
