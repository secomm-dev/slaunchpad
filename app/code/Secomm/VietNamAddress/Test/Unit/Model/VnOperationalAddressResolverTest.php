<?php
declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;
use Secomm\VietNamAddress\Api\Data\VnOperationalResolutionInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Model\Data\VnAddressUnitData;
use Secomm\VietNamAddress\Model\Scheme\VnSchemeRegistry;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;
use Secomm\VietNamAddress\Model\VnOperationalAddressResolver;

/**
 * DEC-FEATYA2C0W-004 (D5) / TASK-Q4B98P — operational ↔ canonical identity bridge:
 * code-based both directions, active-scheme + registry drift gate, no name join anywhere
 * (SPEC-FEAT-YA2C0W-canonical-identity-bridge §7 — "no name lookup performed" is asserted
 * structurally: only directory_region / directory_region_city appear in the built SQL).
 */
class VnOperationalAddressResolverTest extends TestCase
{
    private const SCHEME = VnSchemes::VN_ADMIN_2025;

    private Mysql&MockObject $adapter;
    private ScopeConfigInterface&MockObject $scopeConfig;
    private VnSchemeRegistry&MockObject $schemeRegistry;
    private VnAddressUnitProviderInterface&MockObject $unitProvider;

    /** @var array<int, mixed> queued fetchRow results */
    private array $fetchRowQueue = [];
    /** @var array<int, mixed> queued fetchCol results */
    private array $fetchColQueue = [];
    /** @var array<int, mixed> queued fetchOne results */
    private array $fetchOneQueue = [];
    /** @var array<int, string> every SQL string built through the adapter */
    private array $sqlBuilt = [];

    private VnOperationalAddressResolver $resolver;

    protected function setUp(): void
    {
        $this->fetchRowQueue = [];
        $this->fetchColQueue = [];
        $this->fetchOneQueue = [];
        $this->sqlBuilt = [];

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnCallback(
            function (array|string $table, mixed $cols = null) use ($select): Select {
                $this->sqlBuilt[] = is_array($table) ? implode(',', array_keys($table)) : (string)$table;

                return $select;
            }
        );
        $select->method('where')->willReturnCallback(fn (): Select => $select);
        $select->method('join')->willReturnCallback(fn (): Select => $select);
        $select->method('joinLeft')->willReturnCallback(fn (): Select => $select);

        $this->adapter = $this->createMock(Mysql::class);
        $this->adapter->method('select')->willReturn($select);
        $this->adapter->method('fetchRow')->willReturnCallback(function () {
            return array_shift($this->fetchRowQueue) ?? false;
        });
        $this->adapter->method('fetchCol')->willReturnCallback(function () {
            return array_shift($this->fetchColQueue) ?? [];
        });
        $this->adapter->method('fetchOne')->willReturnCallback(function () {
            return array_shift($this->fetchOneQueue) ?? false;
        });

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->adapter);
        $resource->method('getTableName')->willReturnCallback(static fn (string $name): string => $name);

        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->scopeConfig->method('getValue')->willReturn(self::SCHEME);
        $this->schemeRegistry = $this->createMock(VnSchemeRegistry::class);
        $this->schemeRegistry->method('getCurrent')->willReturn(self::SCHEME);
        $this->unitProvider = $this->createMock(VnAddressUnitProviderInterface::class);

        $this->resolver = new VnOperationalAddressResolver(
            $resource,
            $this->scopeConfig,
            $this->schemeRegistry,
            $this->unitProvider
        );
    }

    private function unit(
        string $code,
        int $level,
        string $regionCode = 'VN-01',
        ?string $parent = null
    ): VnAddressUnitInterface {
        return new VnAddressUnitData(self::SCHEME, $code, $parent, $regionCode, $level, 'Tên', 'Name');
    }

    // ---------------------------------------------------------------- forward

    public function testInvalidInputResolvesWithoutAnyQuery(): void
    {
        $result = $this->resolver->resolveFromRuntime(0, 0);

        $this->assertFalse($result->isResolved());
        $this->assertSame(VnOperationalResolutionInterface::REASON_INVALID_INPUT, $result->getReason());
        $this->assertSame([], $this->sqlBuilt);
    }

    public function testForwardCityResolvesCanonicalIdentityWithoutNameLookup(): void
    {
        $this->fetchRowQueue = [
            ['city_id' => 77, 'region_id' => 5, 'code' => 'VNA25-3D6A6CF4D0', 'parent_city_id' => null],
            ['region_id' => 5, 'country_id' => 'VN', 'code' => 'VN-01'],
        ];
        $this->unitProvider->method('getUnit')->willReturn($this->unit('VNA25-3D6A6CF4D0', 2));

        $result = $this->resolver->resolveFromRuntime(5, 77);

        $this->assertTrue($result->isResolved());
        $identity = $result->getIdentity();
        $this->assertSame(self::SCHEME, $identity->getSchemeCode());
        $this->assertSame('VNA25-3D6A6CF4D0', $identity->getUnitCode());
        $this->assertSame(2, $identity->getLevel());
        $this->assertSame('VN-01', $identity->getRegionCode());
        $this->assertSame(5, $identity->getRegionId());
        $this->assertSame(77, $identity->getCityId());
        // Name tables are never touched (AC / SPEC §3.4).
        $this->assertStringContainsString('directory_region_city', implode('|', $this->sqlBuilt));
        $this->assertStringContainsString('directory_country_region', implode('|', $this->sqlBuilt));
    }

    public function testForwardRegionOnlyResolvesRegionUnit(): void
    {
        $this->fetchRowQueue = [
            ['region_id' => 5, 'country_id' => 'VN', 'code' => 'VN-01'],
        ];
        $this->unitProvider->method('getUnit')->willReturn($this->unit('VN-01', 1));

        $result = $this->resolver->resolveFromRuntime(5);

        $this->assertTrue($result->isResolved());
        $this->assertSame('VN-01', $result->getIdentity()->getUnitCode());
        $this->assertSame(1, $result->getIdentity()->getLevel());
        $this->assertNull($result->getIdentity()->getCityId());
    }

    public function testForwardCityDerivesRegionWhenRegionIdOmitted(): void
    {
        $this->fetchRowQueue = [
            ['city_id' => 77, 'region_id' => 5, 'code' => 'VNA25-3D6A6CF4D0', 'parent_city_id' => null],
            ['region_id' => 5, 'country_id' => 'VN', 'code' => 'VN-01'],
        ];
        $this->unitProvider->method('getUnit')->willReturn($this->unit('VNA25-3D6A6CF4D0', 2));

        $result = $this->resolver->resolveFromRuntime(0, 77);

        $this->assertTrue($result->isResolved());
        $this->assertSame(5, $result->getIdentity()->getRegionId());
    }

    public function testForwardMissingCityRowIsExplicit(): void
    {
        $this->fetchRowQueue = [false];

        $result = $this->resolver->resolveFromRuntime(5, 999999);

        $this->assertFalse($result->isResolved());
        $this->assertSame(VnOperationalResolutionInterface::REASON_RUNTIME_ROW_MISSING, $result->getReason());
    }

    public function testForwardRegionCityMismatchIsInvalidInput(): void
    {
        $this->fetchRowQueue = [
            ['city_id' => 77, 'region_id' => 6, 'code' => 'VNA25-3D6A6CF4D0', 'parent_city_id' => null],
        ];

        $result = $this->resolver->resolveFromRuntime(5, 77);

        $this->assertFalse($result->isResolved());
        $this->assertSame(VnOperationalResolutionInterface::REASON_INVALID_INPUT, $result->getReason());
    }

    public function testForwardNullCodeIsExplicitNotNameGuessed(): void
    {
        $this->fetchRowQueue = [
            ['city_id' => 77, 'region_id' => 5, 'code' => null, 'parent_city_id' => null],
            ['region_id' => 5, 'country_id' => 'VN', 'code' => 'VN-01'],
        ];
        $this->unitProvider->expects($this->never())->method('getUnit');

        $result = $this->resolver->resolveFromRuntime(5, 77);

        $this->assertFalse($result->isResolved());
        $this->assertSame(VnOperationalResolutionInterface::REASON_RUNTIME_CODE_MISSING, $result->getReason());
    }

    public function testForwardNonVnRegionIsExplicit(): void
    {
        $this->fetchRowQueue = [
            ['region_id' => 9, 'country_id' => 'US', 'code' => 'CA'],
        ];

        $result = $this->resolver->resolveFromRuntime(9);

        $this->assertFalse($result->isResolved());
        $this->assertSame(VnOperationalResolutionInterface::REASON_NOT_VN_REGION, $result->getReason());
    }

    // ---------------------------------------------------------------- drift (AC-9)

    public function testConfigRegistryDriftYieldsSchemeNotActive(): void
    {
        $this->schemeRegistry = $this->createMock(VnSchemeRegistry::class);
        $this->schemeRegistry->method('getCurrent')->willReturn(VnSchemes::VN_ADMIN_PRE_2025);
        $this->resolver = new VnOperationalAddressResolver(
            $this->createMock(ResourceConnection::class),
            $this->scopeConfig,
            $this->schemeRegistry,
            $this->unitProvider
        );

        $result = $this->resolver->resolveFromRuntime(5, 77);

        $this->assertFalse($result->isResolved());
        $this->assertSame(VnOperationalResolutionInterface::REASON_SCHEME_NOT_ACTIVE, $result->getReason());
    }

    public function testEmptyConfigYieldsSchemeNotActive(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn(null);
        $this->resolver = new VnOperationalAddressResolver(
            $this->createMock(ResourceConnection::class),
            $scopeConfig,
            $this->schemeRegistry,
            $this->unitProvider
        );

        $result = $this->resolver->resolveFromRuntime(5);

        $this->assertFalse($result->isResolved());
        $this->assertSame(VnOperationalResolutionInterface::REASON_SCHEME_NOT_ACTIVE, $result->getReason());
    }

    // ---------------------------------------------------------------- reverse

    public function testReverseActiveSchemeResolvesRuntimeIdentity(): void
    {
        $this->unitProvider->method('getUnit')->willReturn($this->unit('VNA25-3D6A6CF4D0', 2, 'VN-01', 'VN-01'));
        $this->fetchColQueue = [[77]];
        $this->fetchOneQueue = [5];

        $result = $this->resolver->resolveFromCanonical(self::SCHEME, 'VNA25-3D6A6CF4D0');

        $this->assertTrue($result->isResolved());
        $identity = $result->getIdentity();
        $this->assertSame('VNA25-3D6A6CF4D0', $identity->getUnitCode());
        $this->assertSame(5, $identity->getRegionId());
        $this->assertSame(77, $identity->getCityId());
        $this->assertSame('VN-01', $identity->getParentUnitCode());
    }

    public function testReverseRegionLevelResolvesRegionIdOnly(): void
    {
        $this->unitProvider->method('getUnit')->willReturn($this->unit('VN-01', 1));
        $this->fetchRowQueue = [['region_id' => 5]];

        $result = $this->resolver->resolveFromCanonical(self::SCHEME, 'VN-01');

        $this->assertTrue($result->isResolved());
        $this->assertSame(5, $result->getIdentity()->getRegionId());
        $this->assertNull($result->getIdentity()->getCityId());
    }

    public function testReverseOtherSchemeNeverFabricatesIds(): void
    {
        $this->unitProvider->expects($this->never())->method('getUnit');

        $result = $this->resolver->resolveFromCanonical(VnSchemes::VN_ADMIN_PRE_2025, 'VNAP25-515AFEF59D');

        $this->assertFalse($result->isResolved());
        $this->assertSame(VnOperationalResolutionInterface::REASON_SCHEME_NOT_ACTIVE, $result->getReason());
        $this->assertNull($result->getIdentity());
    }

    public function testReverseUnknownSchemeCodeIsInvalidInput(): void
    {
        $result = $this->resolver->resolveFromCanonical('VN_ADMIN_1999', 'VN-01');

        $this->assertFalse($result->isResolved());
        $this->assertSame(VnOperationalResolutionInterface::REASON_INVALID_INPUT, $result->getReason());
    }

    public function testReverseUnknownUnitIsExplicit(): void
    {
        $this->unitProvider->method('getUnit')->willReturn(null);

        $result = $this->resolver->resolveFromCanonical(self::SCHEME, 'VNA25-0000000000');

        $this->assertFalse($result->isResolved());
        $this->assertSame(VnOperationalResolutionInterface::REASON_UNIT_UNKNOWN, $result->getReason());
    }

    public function testReverseMissingRuntimeRowIsExplicit(): void
    {
        $this->unitProvider->method('getUnit')->willReturn($this->unit('VNA25-3D6A6CF4D0', 2));
        $this->fetchColQueue = [[]];

        $result = $this->resolver->resolveFromCanonical(self::SCHEME, 'VNA25-3D6A6CF4D0');

        $this->assertFalse($result->isResolved());
        $this->assertSame(VnOperationalResolutionInterface::REASON_RUNTIME_ROW_MISSING, $result->getReason());
    }

    public function testReverseAmbiguousRuntimeRowsNeverGuess(): void
    {
        $this->unitProvider->method('getUnit')->willReturn($this->unit('VNA25-3D6A6CF4D0', 2));
        $this->fetchColQueue = [[77, 88]];

        $result = $this->resolver->resolveFromCanonical(self::SCHEME, 'VNA25-3D6A6CF4D0');

        $this->assertFalse($result->isResolved());
        $this->assertSame(VnOperationalResolutionInterface::REASON_RUNTIME_ROW_MISSING, $result->getReason());
    }
}
