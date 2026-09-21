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
use Secomm\VietNamAddress\Api\Data\VnOperationalIdentityInterface;
use Secomm\VietNamAddress\Api\Data\VnOperationalNameResolutionInterface;
use Secomm\VietNamAddress\Api\Data\VnOperationalResolutionInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;
use Secomm\VietNamAddress\Api\VnOperationalAddressResolverInterface;
use Secomm\VietNamAddress\Model\Data\VnAddressUnitData;
use Secomm\VietNamAddress\Model\Scheme\VnSchemeRegistry;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;
use Secomm\VietNamAddress\Model\VnOperationalNameResolver;

/**
 * DEC-FEATYA2C0W-004 (D5 name-entry) / TASK-7AJ3K8 — name-based bridge: reference-layer
 * matching (name_vi OR name_en, region-scoped), AMBIGUOUS carries candidates and NEVER
 * auto-picks, single match composes with the id bridge for the runtime identity.
 */
class VnOperationalNameResolverTest extends TestCase
{
    private const SCHEME = VnSchemes::VN_ADMIN_2025;

    private Mysql&MockObject $adapter;
    private ResourceConnection&MockObject $resource;
    private ScopeConfigInterface&MockObject $scopeConfig;
    private VnSchemeRegistry&MockObject $schemeRegistry;
    private VnAddressUnitProviderInterface&MockObject $unitProvider;
    private VnOperationalAddressResolverInterface&MockObject $operationalResolver;

    /** @var array<int, mixed> queued fetchRow results */
    private array $fetchRowQueue = [];

    private VnOperationalNameResolver $resolver;

    protected function setUp(): void
    {
        $this->fetchRowQueue = [];

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnCallback(fn (): Select => $select);
        $select->method('where')->willReturnCallback(fn (): Select => $select);

        $this->adapter = $this->createMock(Mysql::class);
        $this->adapter->method('select')->willReturn($select);
        $this->adapter->method('fetchRow')->willReturnCallback(function () {
            return array_shift($this->fetchRowQueue) ?? false;
        });

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->adapter);
        $resource->method('getTableName')->willReturnCallback(static fn (string $name): string => $name);
        $this->resource = $resource;

        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->scopeConfig->method('getValue')->willReturn(self::SCHEME);
        $this->schemeRegistry = $this->createMock(VnSchemeRegistry::class);
        $this->schemeRegistry->method('getCurrent')->willReturn(self::SCHEME);
        $this->unitProvider = $this->createMock(VnAddressUnitProviderInterface::class);
        $this->operationalResolver = $this->createMock(VnOperationalAddressResolverInterface::class);

        $this->resolver = new VnOperationalNameResolver(
            $resource,
            $this->scopeConfig,
            $this->schemeRegistry,
            $this->unitProvider,
            $this->operationalResolver
        );
    }

    public function testInvalidInputUnmappedWithoutAnyQuery(): void
    {
        $result = $this->resolver->resolveWardByName(0, '  ');

        $this->assertFalse($result->isResolved());
        $this->assertSame(VnOperationalNameResolutionInterface::REASON_NAME_NOT_MATCHED, $result->getReason());
    }

    public function testConfiguredSchemeNotTrustedOnRegistryDrift(): void
    {
        // Fresh registry mock — the resolver must trust the scheme only when config AND
        // registry agree (same rule as VnOperationalAddressResolver).
        $registry = $this->createMock(VnSchemeRegistry::class);
        $registry->method('getCurrent')->willReturn(VnSchemes::VN_ADMIN_PRE_2025);
        $resolver = new VnOperationalNameResolver(
            $this->resource,
            $this->scopeConfig,
            $registry,
            $this->unitProvider,
            $this->operationalResolver
        );

        $result = $resolver->resolveWardByName(5, 'Phường A');

        $this->assertSame(VnOperationalNameResolutionInterface::STATUS_UNMAPPED, $result->getStatus());
        $this->assertSame(VnOperationalNameResolutionInterface::REASON_SCHEME_NOT_ACTIVE, $result->getReason());
    }

    public function testNonVietnamRegionIsAnExplicitMiss(): void
    {
        $this->fetchRowQueue = [['country_id' => 'US']];

        $result = $this->resolver->resolveWardByName(9, 'Phường A');

        $this->assertSame(VnOperationalNameResolutionInterface::REASON_NOT_VN_REGION, $result->getReason());
    }

    public function testRegionRuntimeRowMissingIsAnExplicitMiss(): void
    {
        $this->fetchRowQueue = [['country_id' => 'VN']];
        $this->operationalResolver->method('resolveFromRuntime')
            ->willReturn($this->unresolved(VnOperationalResolutionInterface::REASON_RUNTIME_ROW_MISSING));

        $result = $this->resolver->resolveWardByName(999, 'Phường A');

        $this->assertSame(VnOperationalNameResolutionInterface::REASON_NOT_VN_REGION, $result->getReason());
    }

    public function testViNameSingleMatchComposesTheRuntimeIdentity(): void
    {
        $this->fetchRowQueue = [['country_id' => 'VN']];
        $this->operationalResolver->method('resolveFromRuntime')->with(5, 0)
            ->willReturn($this->resolvedRegionIdentity('VN-01'));
        $this->unitProvider->method('getChildren')->with(self::SCHEME, 'VN-01')->willReturn([
            $this->unit('VNA25-AAAA', 2, 'Phường Hàng Trống', 'Hang Trong'),
            $this->unit('VNA25-BBBB', 2, 'Phường Bến Nghé', 'Ben Nghe'),
        ]);
        $identity = $this->createMock(VnOperationalIdentityInterface::class);
        $this->operationalResolver->expects($this->once())->method('resolveFromCanonical')
            ->with(self::SCHEME, 'VNA25-AAAA')
            ->willReturn($this->canonicalResolved($identity));

        $result = $this->resolver->resolveWardByName(5, 'Phường Hàng Trống');

        $this->assertTrue($result->isResolved());
        $this->assertSame(VnOperationalNameResolutionInterface::STATUS_EXACT, $result->getStatus());
        $this->assertSame($identity, $result->getIdentity());
    }

    public function testEnglishNameAlsoMatches(): void
    {
        $this->fetchRowQueue = [['country_id' => 'VN']];
        $this->operationalResolver->method('resolveFromRuntime')->willReturn($this->resolvedRegionIdentity('VN-01'));
        $this->unitProvider->method('getChildren')->willReturn([
            $this->unit('VNA25-BBBB', 2, 'Phường Bến Nghé', 'Ben Nghe'),
        ]);
        $this->operationalResolver->method('resolveFromCanonical')
            ->willReturn($this->canonicalResolved($this->createMock(VnOperationalIdentityInterface::class)));

        $result = $this->resolver->resolveWardByName(5, 'Ben Nghe');

        $this->assertTrue($result->isResolved());
    }

    public function testNameIsTrimmedBeforeMatching(): void
    {
        $this->fetchRowQueue = [['country_id' => 'VN']];
        $this->operationalResolver->method('resolveFromRuntime')->willReturn($this->resolvedRegionIdentity('VN-01'));
        $this->unitProvider->method('getChildren')->willReturn([
            $this->unit('VNA25-BBBB', 2, 'Phường Bến Nghé', 'Ben Nghe'),
        ]);
        $this->operationalResolver->method('resolveFromCanonical')
            ->willReturn($this->canonicalResolved($this->createMock(VnOperationalIdentityInterface::class)));

        $result = $this->resolver->resolveWardByName(5, '  Phường Bến Nghé ');

        $this->assertTrue($result->isResolved());
    }

    public function testAmbiguousNameReturnsSortedCandidatesAndNeverPicks(): void
    {
        $this->fetchRowQueue = [['country_id' => 'VN']];
        $this->operationalResolver->method('resolveFromRuntime')->willReturn($this->resolvedRegionIdentity('VN-01'));
        $this->unitProvider->method('getChildren')->willReturn([
            $this->unit('VNA25-ZZZZ', 2, 'Phường Trần Phú', 'Tran Phu'),
            $this->unit('VNA25-AAAA', 2, 'Phường Trần Phú', 'Tran Phu'),
        ]);
        $this->operationalResolver->expects($this->never())->method('resolveFromCanonical');

        $result = $this->resolver->resolveWardByName(5, 'Phường Trần Phú');

        $this->assertFalse($result->isResolved());
        $this->assertSame(VnOperationalNameResolutionInterface::STATUS_AMBIGUOUS, $result->getStatus());
        $this->assertSame(['VNA25-AAAA', 'VNA25-ZZZZ'], $result->getCandidateCodes());
    }

    public function testUnknownNameIsUnmapped(): void
    {
        $this->fetchRowQueue = [['country_id' => 'VN']];
        $this->operationalResolver->method('resolveFromRuntime')->willReturn($this->resolvedRegionIdentity('VN-01'));
        $this->unitProvider->method('getChildren')->willReturn([
            $this->unit('VNA25-AAAA', 2, 'Phường Hàng Trống', 'Hang Trong'),
        ]);

        $result = $this->resolver->resolveWardByName(5, 'Phường Không Có');

        $this->assertSame(VnOperationalNameResolutionInterface::STATUS_UNMAPPED, $result->getStatus());
        $this->assertSame(VnOperationalNameResolutionInterface::REASON_NAME_NOT_MATCHED, $result->getReason());
    }

    public function testCanonicalUnitWithoutRuntimeRowIsUnmapped(): void
    {
        $this->fetchRowQueue = [['country_id' => 'VN']];
        $this->operationalResolver->method('resolveFromRuntime')->willReturn($this->resolvedRegionIdentity('VN-01'));
        $this->unitProvider->method('getChildren')->willReturn([
            $this->unit('VNA25-GONE', 2, 'Phường Đã Xoá', 'Gone Ward'),
        ]);
        $this->operationalResolver->method('resolveFromCanonical')
            ->willReturn($this->unresolved(VnOperationalResolutionInterface::REASON_RUNTIME_ROW_MISSING));

        $result = $this->resolver->resolveWardByName(5, 'Phường Đã Xoá');

        $this->assertFalse($result->isResolved());
        $this->assertSame(VnOperationalNameResolutionInterface::REASON_RUNTIME_ROW_MISSING, $result->getReason());
    }

    public function testRegionLevelChildrenAreNeverWardMatches(): void
    {
        $this->fetchRowQueue = [['country_id' => 'VN']];
        $this->operationalResolver->method('resolveFromRuntime')->willReturn($this->resolvedRegionIdentity('VN-01'));
        $this->unitProvider->method('getChildren')->willReturn([
            $this->unit('VN-01', 1, 'Hà Nội', 'Hanoi'),
        ]);

        $result = $this->resolver->resolveWardByName(5, 'Hà Nội');

        $this->assertSame(VnOperationalNameResolutionInterface::REASON_NAME_NOT_MATCHED, $result->getReason());
    }

    // ------------------------------------------------------------------ helpers

    private function unit(string $code, int $level, string $nameVi, string $nameEn): VnAddressUnitInterface
    {
        return new VnAddressUnitData(self::SCHEME, $code, null, 'VN-01', $level, $nameVi, $nameEn);
    }

    private function resolvedRegionIdentity(string $regionCode): VnOperationalResolutionInterface&MockObject
    {
        $identity = $this->createMock(VnOperationalIdentityInterface::class);
        $identity->method('getSchemeCode')->willReturn(self::SCHEME);
        $identity->method('getUnitCode')->willReturn($regionCode);
        $identity->method('getRegionCode')->willReturn($regionCode);

        $result = $this->createMock(VnOperationalResolutionInterface::class);
        $result->method('isResolved')->willReturn(true);
        $result->method('getIdentity')->willReturn($identity);

        return $result;
    }

    private function canonicalResolved(VnOperationalIdentityInterface $identity): VnOperationalResolutionInterface&MockObject
    {
        $result = $this->createMock(VnOperationalResolutionInterface::class);
        $result->method('isResolved')->willReturn(true);
        $result->method('getIdentity')->willReturn($identity);

        return $result;
    }

    private function unresolved(string $reason): VnOperationalResolutionInterface&MockObject
    {
        $result = $this->createMock(VnOperationalResolutionInterface::class);
        $result->method('isResolved')->willReturn(false);
        $result->method('getReason')->willReturn($reason);

        return $result;
    }
}
