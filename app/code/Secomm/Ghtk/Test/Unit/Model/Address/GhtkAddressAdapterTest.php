<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Address;

use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Api\Data\GhtkAddressOverrideInterface;
use Secomm\Ghtk\Model\Address\GhtkAddressAdapter;
use Secomm\Ghtk\Model\Address\GhtkLegacyCapabilityShim;
use Secomm\Ghtk\Model\Address\GhtkOperationAddressCapability;
use Secomm\Ghtk\Model\GhtkAddressOverride;
use Secomm\Ghtk\Model\GhtkAddressOverrideRepository;
use Secomm\Ghtk\Model\GhtkApiProfile;
use Secomm\Ghtk\Model\Log\MaskingLogger;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffInterface;
use Secomm\ShippingCore\Api\Address\CarrierAddressHandoffServiceInterface;
use Secomm\ShippingCore\Api\Address\ResolvedShippingAddressInterface;
use Secomm\ShippingCore\Api\Address\RuntimeAddressContextBuilderInterface;
use Secomm\ShippingCore\Api\Address\ShippingAddressOperation;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface;
use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;

/**
 * TASK-7AJ3K8 r1 (DEC-TASK7AJ3K8-002) — TEXT_NATIVE address adapter: canonical name_vi is
 * the DEFAULT representation; `secomm_ghtk_address_map` is an optional exception override
 * keyed by canonical identity; AMBIGUOUS/UNMAPPED → null (never a guessed address).
 */
class GhtkAddressAdapterTest extends TestCase
{
    private const SCHEME = 'VN_ADMIN_2025';
    private const UNIT = 'VNA25-AAAA';
    private const PROVINCE_CODE = 'VN-01';

    private RuntimeAddressContextBuilderInterface&MockObject $contextBuilder;
    private CarrierAddressHandoffServiceInterface&MockObject $handoffService;
    private VnAddressUnitProviderInterface&MockObject $unitProvider;
    private GhtkAddressOverrideRepository&MockObject $overrideRepository;
    private CacheInterface&MockObject $cache;
    private MaskingLogger&MockObject $logger;
    private GhtkAddressAdapter $adapter;

    protected function setUp(): void
    {
        $this->contextBuilder = $this->createMock(RuntimeAddressContextBuilderInterface::class);
        $this->handoffService = $this->createMock(CarrierAddressHandoffServiceInterface::class);
        $this->unitProvider = $this->createMock(VnAddressUnitProviderInterface::class);
        $this->overrideRepository = $this->createMock(GhtkAddressOverrideRepository::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->cache->method('load')->willReturn(false); // default: cache miss
        $this->logger = $this->createMock(MaskingLogger::class);

        $this->adapter = $this->makeAdapter($this->cache);
    }

    private function makeAdapter(CacheInterface $cache): GhtkAddressAdapter
    {
        $capability = new GhtkOperationAddressCapability(new GhtkApiProfile());

        return new GhtkAddressAdapter(
            $this->contextBuilder,
            $this->handoffService,
            $capability,
            new GhtkLegacyCapabilityShim($capability),
            $this->unitProvider,
            $this->overrideRepository,
            $cache,
            $this->logger
        );
    }

    // ------------------------------------------------------------ canonical text (default)

    public function testCanonicalNameViIsTheDefaultRepresentation(): void
    {
        $this->handoffWillResolve(self::UNIT);
        $this->unitProvider->method('getUnit')->willReturnCallback(
            fn (string $scheme, string $code): ?VnAddressUnitInterface => match ($code) {
                self::UNIT => $this->unit(self::UNIT, 2, 'Phường Hàng Trống', self::PROVINCE_CODE),
                self::PROVINCE_CODE => $this->unit(self::PROVINCE_CODE, 1, 'Hà Nội', self::PROVINCE_CODE),
                default => null,
            }
        );
        $this->overrideRepository->method('findActive')->willReturn(null);

        $address = $this->adapter->resolve('VN', 5, null, 'Phường Hàng Trống', ShippingAddressOperation::RATE);

        $this->assertNotNull($address);
        $this->assertFalse($address->isExact);
        $this->assertSame('Hà Nội', $address->province);
        $this->assertNull($address->district);
        $this->assertSame('Phường Hàng Trống', $address->ward);
    }

    public function testCanonicalWardNameInAnotherProvinceStillResolvesRegionScoped(): void
    {
        // Region-scoped resolution means a ward name that also exists elsewhere is fine —
        // the identity is canonical, not the name.
        $this->handoffWillResolve('VNA25-BBBB');
        $this->unitProvider->method('getUnit')->willReturnCallback(
            fn (string $scheme, string $code): ?VnAddressUnitInterface => match ($code) {
                'VNA25-BBBB' => $this->unit('VNA25-BBBB', 2, 'Phường Trần Phú', 'VN-92'),
                'VN-92' => $this->unit('VN-92', 1, 'Thanh Hóa', 'VN-92'),
                default => null,
            }
        );
        $this->overrideRepository->method('findActive')->willReturn(null);

        $address = $this->adapter->resolve('VN', 551, null, 'Phường Trần Phú', ShippingAddressOperation::RATE);

        $this->assertNotNull($address);
        $this->assertSame('Thanh Hóa', $address->province);
        $this->assertSame('Phường Trần Phú', $address->ward);
    }

    // ------------------------------------------------------------ overrides (exception layer)

    public function testProvinceAndWardOverrideReplaceCanonicalText(): void
    {
        $this->handoffWillResolve(self::UNIT);
        $this->unitProvider->method('getUnit')->willReturnCallback(
            fn (string $scheme, string $code): ?VnAddressUnitInterface => match ($code) {
                self::UNIT => $this->unit(self::UNIT, 2, 'Phường Hàng Trống', self::PROVINCE_CODE),
                self::PROVINCE_CODE => $this->unit(self::PROVINCE_CODE, 1, 'Hà Nội', self::PROVINCE_CODE),
                default => null,
            }
        );
        $this->overrideRepository->expects($this->once())->method('findActive')
            ->with(self::SCHEME, self::PROVINCE_CODE, self::UNIT)
            ->willReturn($this->override('GHTK Hà Nội', null, 'GHTK Hàng Trống'));

        $address = $this->adapter->resolve('VN', 5, null, 'Phường Hàng Trống', ShippingAddressOperation::RATE);

        $this->assertNotNull($address);
        $this->assertTrue($address->isExact);
        $this->assertSame('GHTK Hà Nội', $address->province);
        $this->assertNull($address->district);
        $this->assertSame('GHTK Hàng Trống', $address->ward);
    }

    public function testWardOnlyPartialOverrideKeepsCanonicalProvince(): void
    {
        $this->handoffWillResolve(self::UNIT);
        $this->unitProvider->method('getUnit')->willReturnCallback(
            fn (string $scheme, string $code): ?VnAddressUnitInterface => match ($code) {
                self::UNIT => $this->unit(self::UNIT, 2, 'Phường Hàng Trống', self::PROVINCE_CODE),
                self::PROVINCE_CODE => $this->unit(self::PROVINCE_CODE, 1, 'Hà Nội', self::PROVINCE_CODE),
                default => null,
            }
        );
        $this->overrideRepository->method('findActive')
            ->willReturn($this->override(null, 'Hoàn Kiếm (legacy)', 'Hàng Trống (legacy)'));

        $address = $this->adapter->resolve('VN', 5, null, 'Phường Hàng Trống', ShippingAddressOperation::RATE);

        $this->assertNotNull($address);
        $this->assertTrue($address->isExact);
        $this->assertSame('Hà Nội', $address->province); // canonical kept — partial override
        $this->assertSame('Hoàn Kiếm (legacy)', $address->district);
        $this->assertSame('Hàng Trống (legacy)', $address->ward);
    }

    public function testDistrictOnlyOverrideAddsDistrictToTheTwoLevelModel(): void
    {
        $this->handoffWillResolve(self::UNIT);
        $this->unitProvider->method('getUnit')->willReturnCallback(
            fn (string $scheme, string $code): ?VnAddressUnitInterface => match ($code) {
                self::UNIT => $this->unit(self::UNIT, 2, 'Phường Hàng Trống', self::PROVINCE_CODE),
                self::PROVINCE_CODE => $this->unit(self::PROVINCE_CODE, 1, 'Hà Nội', self::PROVINCE_CODE),
                default => null,
            }
        );
        $this->overrideRepository->method('findActive')->willReturn($this->override(null, 'Hoàn Kiếm', null));

        $address = $this->adapter->resolve('VN', 5, null, 'Phường Hàng Trống', ShippingAddressOperation::RATE);

        $this->assertNotNull($address);
        $this->assertSame('Hoàn Kiếm', $address->district);
        $this->assertSame('Phường Hàng Trống', $address->ward);
    }

    public function testInactiveOverrideRowsAreIgnoredByTheRepository(): void
    {
        // findActive only returns active rows — a disabled override means pure canonical text.
        $this->handoffWillResolve(self::UNIT);
        $this->unitProvider->method('getUnit')->willReturnCallback(
            fn (string $scheme, string $code): ?VnAddressUnitInterface => match ($code) {
                self::UNIT => $this->unit(self::UNIT, 2, 'Phường Hàng Trống', self::PROVINCE_CODE),
                self::PROVINCE_CODE => $this->unit(self::PROVINCE_CODE, 1, 'Hà Nội', self::PROVINCE_CODE),
                default => null,
            }
        );
        $this->overrideRepository->method('findActive')->willReturn(null);

        $address = $this->adapter->resolve('VN', 5, null, 'Phường Hàng Trống', ShippingAddressOperation::RATE);

        $this->assertNotNull($address);
        $this->assertFalse($address->isExact);
        $this->assertSame('Phường Hàng Trống', $address->ward);
    }

    public function testEmptyOverrideTextFallsBackToCanonicalPerField(): void
    {
        // Model normalizes '' to null — an override row with empty strings keeps canonical text.
        $this->handoffWillResolve(self::UNIT);
        $this->unitProvider->method('getUnit')->willReturnCallback(
            fn (string $scheme, string $code): ?VnAddressUnitInterface => match ($code) {
                self::UNIT => $this->unit(self::UNIT, 2, 'Phường Hàng Trống', self::PROVINCE_CODE),
                self::PROVINCE_CODE => $this->unit(self::PROVINCE_CODE, 1, 'Hà Nội', self::PROVINCE_CODE),
                default => null,
            }
        );
        $this->overrideRepository->method('findActive')->willReturn($this->override('', '', ''));

        $address = $this->adapter->resolve('VN', 5, null, 'Phường Hàng Trống', ShippingAddressOperation::RATE);

        $this->assertNotNull($address);
        $this->assertSame('Hà Nội', $address->province);
        $this->assertSame('Phường Hàng Trống', $address->ward);
    }

    // ------------------------------------------------------------ fail-closed (no guessed address)

    public function testAmbiguousCanonicalOutcomeNeverSendsARequest(): void
    {
        $handoff = $this->createMock(CarrierAddressHandoffInterface::class);
        $handoff->method('isApplicable')->willReturn(true);
        $handoff->method('getResolvedAddress')->willReturn(null);
        $handoff->method('getCandidateCodes')->willReturn(['VNA25-AAAA', 'VNA25-BBBB']);
        $this->handoffWillReturn($handoff);

        $this->unitProvider->expects($this->never())->method('getUnit');
        $this->overrideRepository->expects($this->never())->method('findActive');

        $this->assertNull($this->adapter->resolve('VN', 5, null, 'Phường Trần Phú', ShippingAddressOperation::RATE));
    }

    public function testUnmappedCanonicalOutcomeNeverSendsAGuessedAddress(): void
    {
        // The r0 best-effort fallback (region name + submitted ward text) is GONE.
        $handoff = $this->createMock(CarrierAddressHandoffInterface::class);
        $handoff->method('isApplicable')->willReturn(true);
        $handoff->method('getResolvedAddress')->willReturn(null);
        $handoff->method('getCandidateCodes')->willReturn([]);
        $this->handoffWillReturn($handoff);

        $this->assertNull($this->adapter->resolve('VN', 5, null, 'Khu phố chưa map', ShippingAddressOperation::RATE));
    }

    public function testNonApplicableDestinationYieldsNull(): void
    {
        $handoff = $this->createMock(CarrierAddressHandoffInterface::class);
        $handoff->method('isApplicable')->willReturn(false);
        $this->handoffWillReturn($handoff);

        $this->assertNull($this->adapter->resolve('US', 12, null, 'Some Ward', ShippingAddressOperation::RATE));
    }

    public function testCanonicalUnitMissingFromReferenceLayerIsInvalid(): void
    {
        $this->handoffWillResolve('VNA25-GONE');
        $this->unitProvider->method('getUnit')->willReturn(null);
        $this->overrideRepository->expects($this->never())->method('findActive');

        $this->assertNull($this->adapter->resolve('VN', 5, null, 'Phường Đã Xoá', ShippingAddressOperation::RATE));
    }

    public function testIncompleteCanonicalNamesAreInvalid(): void
    {
        $this->handoffWillResolve(self::UNIT);
        $this->unitProvider->method('getUnit')->willReturnCallback(
            fn (string $scheme, string $code): ?VnAddressUnitInterface => match ($code) {
                self::UNIT => $this->unit(self::UNIT, 2, '', self::PROVINCE_CODE),
                self::PROVINCE_CODE => $this->unit(self::PROVINCE_CODE, 1, 'Hà Nội', self::PROVINCE_CODE),
                default => null,
            }
        );

        $this->assertNull($this->adapter->resolve('VN', 5, null, 'Phường Vô Danh', ShippingAddressOperation::RATE));
    }

    public function testUnexpectedFailureYieldsNullNeverThrows(): void
    {
        $this->contextBuilder->method('build')->willThrowException(new \RuntimeException('boom'));

        $this->assertNull($this->adapter->resolve('VN', 5, null, 'Phường A', ShippingAddressOperation::RATE));
    }

    /**
     * TASK-6YG3HP — RATE and CREATE each drive their OWN operation-specific
     * handoff; the operation is never inferred downstream.
     */
    public function testOperationIsForwardedToTheOperationSpecificHandoff(): void
    {
        $captured = [];
        $this->handoffWillResolve(self::UNIT);
        $this->handoffService->method('handoffContextForOperation')
            ->willReturnCallback(function ($context, $capability, $operation) use (&$captured) {
                $captured[] = $operation;

                return $this->resolvedHandoff(self::UNIT);
            });
        $this->unitProvider->method('getUnit')->willReturn(null);
        $this->overrideRepository->method('findActive')->willReturn(null);

        $this->adapter->resolve('VN', 5, null, 'Phường Hàng Trống', ShippingAddressOperation::RATE);
        $this->adapter->resolve('VN', 5, null, 'Phường Hàng Trống', ShippingAddressOperation::CREATE);

        $this->assertSame([ShippingAddressOperation::RATE, ShippingAddressOperation::CREATE], $captured);
    }

    // ------------------------------------------------------------ cache

    public function testResolvedResultIsCachedByUnitCode(): void
    {
        $this->handoffWillResolve(self::UNIT);
        $this->unitProvider->method('getUnit')->willReturnCallback(
            fn (string $scheme, string $code): ?VnAddressUnitInterface => match ($code) {
                self::UNIT => $this->unit(self::UNIT, 2, 'Phường Hàng Trống', self::PROVINCE_CODE),
                self::PROVINCE_CODE => $this->unit(self::PROVINCE_CODE, 1, 'Hà Nội', self::PROVINCE_CODE),
                default => null,
            }
        );
        $this->overrideRepository->method('findActive')->willReturn(null);
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $this->cache = $cache;
        $adapter = $this->makeAdapter($cache);
        $cache->expects($this->once())->method('save')->with(
            $this->callback(fn (string $payload): bool => str_contains($payload, '"isExact":false')),
            'ghtk_dest2_u_' . self::UNIT,
            $this->anything(),
            $this->anything()
        );

        $adapter->resolve('VN', 5, null, 'Phường Hàng Trống', ShippingAddressOperation::RATE);
    }

    public function testCachedResultSkipsReferenceLayerAndOverrides(): void
    {
        $this->handoffWillResolve(self::UNIT);
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn(
            (string) json_encode(['province' => 'P', 'district' => null, 'ward' => 'W', 'isExact' => true])
        );
        $adapter = $this->makeAdapter($cache);
        $this->unitProvider->expects($this->never())->method('getUnit');
        $this->overrideRepository->expects($this->never())->method('findActive');

        $address = $adapter->resolve('VN', 5, null, 'Phường A', ShippingAddressOperation::RATE);

        $this->assertSame('W', $address?->ward);
        $this->assertTrue($address->isExact);
    }

    // ------------------------------------------------------------ helpers

    private function handoffWillResolve(string $unitCode): void
    {
        $resolved = $this->createMock(ResolvedShippingAddressInterface::class);
        $resolved->method('isResolved')->willReturn(true);
        $resolved->method('getSchemeCode')->willReturn(self::SCHEME);
        $resolved->method('getUnitCode')->willReturn($unitCode);
        $resolved->method('getCandidateCodes')->willReturn([]);

        $handoff = $this->createMock(CarrierAddressHandoffInterface::class);
        $handoff->method('isApplicable')->willReturn(true);
        $handoff->method('getResolvedAddress')->willReturn($resolved);
        $handoff->method('getCandidateCodes')->willReturn([]);

        $this->handoffWillReturn($handoff);
    }

    private function handoffWillReturn(CarrierAddressHandoffInterface $handoff): void
    {
        $this->contextBuilder->method('build')->willReturn(
            $this->createMock(ShippingAddressResolutionContextInterface::class)
        );
        $this->handoffService->method('handoffContextForOperation')->willReturn($handoff);
    }

    private function unit(string $code, int $level, string $nameVi, string $regionCode): VnAddressUnitInterface&MockObject
    {
        $unit = $this->createMock(VnAddressUnitInterface::class);
        $unit->method('getCode')->willReturn($code);
        $unit->method('getNameVi')->willReturn($nameVi);
        $unit->method('getNameEn')->willReturn(strtoupper($nameVi));
        $unit->method('getRegionCode')->willReturn($regionCode);
        $unit->method('getLevel')->willReturn($level);

        return $unit;
    }

    private function override(
        ?string $province,
        ?string $district,
        ?string $ward
    ): GhtkAddressOverrideInterface&MockObject {
        $override = $this->createMock(GhtkAddressOverride::class);
        $override->method('getGhtkProvince')->willReturn($province !== '' ? $province : null);
        $override->method('getGhtkDistrict')->willReturn($district !== '' ? $district : null);
        $override->method('getGhtkWard')->willReturn($ward !== '' ? $ward : null);

        return $override;
    }
}
