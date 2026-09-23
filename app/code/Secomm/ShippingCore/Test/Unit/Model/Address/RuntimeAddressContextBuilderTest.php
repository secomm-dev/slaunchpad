<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Address;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\CarrierAddressCapabilityInterface;
use Secomm\VietNamAddress\Api\Data\VnOperationalIdentityInterface;
use Secomm\VietNamAddress\Api\Data\VnOperationalResolutionInterface;
use Secomm\VietNamAddress\Api\VnOperationalAddressResolverInterface;
use Secomm\VietNamAddress\Api\VnOperationalNameResolverInterface;
use Secomm\VietNamAddress\Model\Data\VnOperationalNameResolutionData;
use Secomm\VietNamAddress\Model\Data\VnOperationalResolutionData;
use Secomm\ShippingCore\Model\Address\RuntimeAddressContextBuilder;

/**
 * TASK-7AJ3K8 — scalar runtime address → resolution context: id-based bridge first, name-based
 * fallback, AMBIGUOUS candidates carried WITHOUT identity, never auto-picked.
 */
class RuntimeAddressContextBuilderTest extends TestCase
{
    private VnOperationalAddressResolverInterface&MockObject $idBridge;
    private VnOperationalNameResolverInterface&MockObject $nameBridge;
    private RuntimeAddressContextBuilder $builder;

    protected function setUp(): void
    {
        $this->idBridge = $this->createMock(VnOperationalAddressResolverInterface::class);
        $this->nameBridge = $this->createMock(VnOperationalNameResolverInterface::class);
        $this->builder = new RuntimeAddressContextBuilder($this->idBridge, $this->nameBridge);
    }

    public function testKnownCityIdTakesTheIdBasedBridge(): void
    {
        $this->idBridge->expects($this->once())->method('resolveFromRuntime')->with(521, 12345)
            ->willReturn(VnOperationalResolutionData::resolved($this->identity('VN_ADMIN_2025', 'VNA25-0A1B')));
        $this->nameBridge->expects($this->never())->method('resolveWardByName');

        $context = $this->builder->build('VN', 521, 12345, 'Phường Hàng Trống', $this->capability());

        $this->assertSame('VN_ADMIN_2025', $context->getSourceScheme());
        $this->assertSame('VNA25-0A1B', $context->getSourceUnitCode());
        $this->assertSame('VN_ADMIN_PRE_2025', $context->getTargetScheme());
        $this->assertSame([], $context->getCandidateCodes());
    }

    public function testMissingCityIdFallsBackToTheNameBridge(): void
    {
        $this->idBridge->expects($this->never())->method('resolveFromRuntime');
        $this->nameBridge->expects($this->once())->method('resolveWardByName')->with(521, 'Phường Hàng Trống')
            ->willReturn(VnOperationalNameResolutionData::exact($this->identity('VN_ADMIN_2025', 'VNA25-0A1B')));

        $context = $this->builder->build('VN', 521, null, 'Phường Hàng Trống', $this->capability());

        $this->assertSame('VN_ADMIN_2025', $context->getSourceScheme());
        $this->assertSame('VNA25-0A1B', $context->getSourceUnitCode());
    }

    public function testAmbiguousNameCarriesCandidatesWithoutIdentity(): void
    {
        $this->nameBridge->method('resolveWardByName')
            ->willReturn(VnOperationalNameResolutionData::ambiguous(['VNA25-0C2D', 'VNA25-0A1B']));

        $context = $this->builder->build('VN', 521, null, 'Phường Hàng Trống', $this->capability());

        $this->assertNull($context->getSourceScheme());
        $this->assertNull($context->getSourceUnitCode());
        $this->assertSame(['VNA25-0A1B', 'VNA25-0C2D'], $context->getCandidateCodes());
    }

    public function testNameMissYieldsAnEmptyIdentityContext(): void
    {
        $this->nameBridge->method('resolveWardByName')
            ->willReturn(VnOperationalNameResolutionData::unmapped('name_not_matched'));

        $context = $this->builder->build('VN', 521, 0, 'Không Tồn Tại', $this->capability());

        $this->assertNull($context->getSourceScheme());
        $this->assertNull($context->getSourceUnitCode());
        $this->assertSame([], $context->getCandidateCodes());
    }

    public function testBlankCityNameSkipsTheNameBridge(): void
    {
        $this->nameBridge->expects($this->never())->method('resolveWardByName');

        $context = $this->builder->build('VN', 521, null, '   ', $this->capability());

        $this->assertNull($context->getSourceScheme());
    }

    public function testUnresolvedIdBridgeDoesNotFallBackToTheName(): void
    {
        // A known city id that fails the bridge is a data fault, not a reason to re-resolve by
        // name — the manager reports UNMAPPED.
        $this->idBridge->method('resolveFromRuntime')
            ->willReturn(VnOperationalResolutionData::unresolved(VnOperationalResolutionInterface::REASON_RUNTIME_ROW_MISSING));
        $this->nameBridge->expects($this->never())->method('resolveWardByName');

        $context = $this->builder->build('VN', 521, 12345, 'Phường Hàng Trống', $this->capability());

        $this->assertNull($context->getSourceScheme());
        $this->assertSame([], $context->getCandidateCodes());
    }

    public function testStreetTextIsTrimmedOrNull(): void
    {
        $this->idBridge->method('resolveFromRuntime')
            ->willReturn(VnOperationalResolutionData::resolved($this->identity('VN_ADMIN_2025', 'VNA25-0A1B')));

        $trimmed = $this->builder->build('VN', 521, 12345, 'P. A', $this->capability(), '  12 Nguyễn Huệ  ');
        $this->assertSame('12 Nguyễn Huệ', $trimmed->getStreetText());

        $blank = $this->builder->build('VN', 521, 12345, 'P. A', $this->capability(), '   ');
        $this->assertNull($blank->getStreetText());
    }

    private function identity(string $scheme, string $unit): VnOperationalIdentityInterface&MockObject
    {
        $identity = $this->createMock(VnOperationalIdentityInterface::class);
        $identity->method('getSchemeCode')->willReturn($scheme);
        $identity->method('getUnitCode')->willReturn($unit);

        return $identity;
    }

    private function capability(): CarrierAddressCapabilityInterface
    {
        $capability = $this->createMock(CarrierAddressCapabilityInterface::class);
        $capability->method('getRequiredScheme')->willReturn('VN_ADMIN_PRE_2025');

        return $capability;
    }
}
