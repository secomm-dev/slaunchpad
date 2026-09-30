<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\ShippingCore\Api\Address\CanonicalZoneInterface;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRegistryInterface;
use Secomm\ShippingCore\Api\Address\DestinationScope;
use Secomm\ShippingCore\Model\Config\CarrierDestinationScopeConfig;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — carrier destination-scope config reader: unset → ALL,
 * zone-code parsing, and the §16/§22 diagnostics for unknown/disabled references under
 * SELECTED_ZONES; TASK-R8WR1R — ALL_EXCEPT_SELECTED_ZONES accepted as-is, same diagnostics with
 * a "does not exclude" hint; TASK-R8WR1R r2 — missing/empty value → documented default (silent),
 * invalid EXPLICIT value → returned verbatim + warning, never coerced to a valid scope
 * (the evaluator's unknown-scope branch is the fail-closed enforcement).
 */
class CarrierDestinationScopeConfigTest extends TestCase
{
    private ScopeConfigInterface $scopeConfig;

    private CanonicalZoneRegistryInterface $zoneRegistry;

    private LoggerInterface $logger;

    private CarrierDestinationScopeConfig $reader;

    private array $configValues = [];

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->scopeConfig->method('getValue')->willReturnCallback(
            function (string $path): mixed {
                return $this->configValues[$path] ?? null;
            }
        );
        $this->zoneRegistry = $this->createMock(CanonicalZoneRegistryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->reader = new CarrierDestinationScopeConfig($this->scopeConfig, $this->zoneRegistry, $this->logger);
    }

    private function setScope(string $scope): void
    {
        $this->configValues['carriers/secomm_ghn/destination_scope'] = $scope;
    }

    private function setZones(mixed $zones): void
    {
        $this->configValues['carriers/secomm_ghn/allowed_zone_codes'] = $zones;
    }

    public function testUnsetScopeIsAll(): void
    {
        $this->assertSame('ALL', $this->reader->getDestinationScope('secomm_ghn'));
    }

    public function testEmptyScopeIsAll(): void
    {
        $this->setScope('  ');
        $this->assertSame('ALL', $this->reader->getDestinationScope('secomm_ghn'));
    }

    public function testSelectedZonesScopeReturned(): void
    {
        $this->setScope('SELECTED_ZONES');
        $this->assertSame('SELECTED_ZONES', $this->reader->getDestinationScope('secomm_ghn'));
    }

    public function testAllExceptSelectedZonesScopeReturnedAsIs(): void
    {
        $this->setScope('ALL_EXCEPT_SELECTED_ZONES');
        $this->logger->expects($this->never())->method('warning');

        $this->assertSame('ALL_EXCEPT_SELECTED_ZONES', $this->reader->getDestinationScope('secomm_ghn'));
    }

    public function testMissingScopeUsesDocumentedDefaultWithoutWarning(): void
    {
        // missing value != invalid explicit value — the documented default applies silently
        $this->logger->expects($this->never())->method('warning');

        $this->assertSame('ALL', $this->reader->getDestinationScope('secomm_ghn'));
    }

    public function testEmptyScopeUsesDocumentedDefaultWithoutWarning(): void
    {
        $this->setScope('   ');
        $this->logger->expects($this->never())->method('warning');

        $this->assertSame('ALL', $this->reader->getDestinationScope('secomm_ghn'));
    }

    public function testUnrecognizedScopeReturnedVerbatimWithFailClosedWarning(): void
    {
        // TASK-R8WR1R r2 — invalid explicit value is never coerced; the raw value reaches the
        // evaluator whose unknown-scope branch fails the carrier closed.
        $this->setScope('SOME_ZONES');
        $this->logger->expects($this->once())->method('warning')
            ->with($this->stringContains('unrecognized destination scope "SOME_ZONES"'));

        $this->assertSame('SOME_ZONES', $this->reader->getDestinationScope('secomm_ghn'));
    }

    public function testInvalidScopeNeverResolvesToAValidScopeValue(): void
    {
        // fail-OPEN regression guard: an invalid explicit value must never read as a valid
        // scope (ALL would make the carrier eligible everywhere).
        $this->setScope('EVERYWHERE');
        $this->logger->expects($this->once())->method('warning');

        $scope = $this->reader->getDestinationScope('secomm_ghn');

        $this->assertFalse(DestinationScope::exists($scope));
        $this->assertNotSame(DestinationScope::ALL, $scope);
        $this->assertNotSame(DestinationScope::SELECTED_ZONES, $scope);
        $this->assertNotSame(DestinationScope::ALL_EXCEPT_SELECTED_ZONES, $scope);
    }

    public function testZonesParsedFromStringCommaSeparated(): void
    {
        $this->setScope('ALL');
        $this->setZones(' HCM_INNER , HCM_OUTER,,HCM_INNER ');

        $codes = $this->reader->getAllowedZoneCodes('secomm_ghn');

        $this->assertSame(['HCM_INNER', 'HCM_OUTER'], $codes);
    }

    public function testZonesParsedFromArray(): void
    {
        $this->setScope('ALL');
        $this->setZones(['HCM_INNER', 'HCM_OUTER']);

        $this->assertSame(['HCM_INNER', 'HCM_OUTER'], $this->reader->getAllowedZoneCodes('secomm_ghn'));
    }

    public function testNoDiagnosticsUnderAll(): void
    {
        $this->setScope('ALL');
        $this->setZones('GHOST_ZONE');
        $this->zoneRegistry->expects($this->never())->method('getByCode');

        $this->assertSame(['GHOST_ZONE'], $this->reader->getAllowedZoneCodes('secomm_ghn'));
    }

    public function testUnknownZoneUnderSelectedZonesWarns(): void
    {
        $this->setScope('SELECTED_ZONES');
        $this->setZones('GHOST_ZONE');
        $this->zoneRegistry->method('getByCode')->willReturn(null);
        $this->logger->expects($this->once())->method('warning')
            ->with($this->stringContains('unknown zone "GHOST_ZONE"'));

        $this->assertSame(['GHOST_ZONE'], $this->reader->getAllowedZoneCodes('secomm_ghn'));
    }

    public function testDisabledZoneUnderSelectedZonesWarns(): void
    {
        $this->setScope('SELECTED_ZONES');
        $this->setZones('HCM_INNER');
        $disabled = $this->createMock(CanonicalZoneInterface::class);
        $disabled->method('isEnabled')->willReturn(false);
        $this->zoneRegistry->method('getByCode')->willReturn($disabled);
        $this->logger->expects($this->once())->method('warning')
            ->with($this->stringContains('disabled zone "HCM_INNER"'));

        $this->assertSame(['HCM_INNER'], $this->reader->getAllowedZoneCodes('secomm_ghn'));
    }

    public function testEnabledZoneUnderSelectedZonesIsSilent(): void
    {
        $this->setScope('SELECTED_ZONES');
        $this->setZones('HCM_INNER');
        $enabled = $this->createMock(CanonicalZoneInterface::class);
        $enabled->method('isEnabled')->willReturn(true);
        $this->zoneRegistry->method('getByCode')->willReturn($enabled);
        $this->logger->expects($this->never())->method('warning');

        $this->assertSame(['HCM_INNER'], $this->reader->getAllowedZoneCodes('secomm_ghn'));
    }

    public function testUnknownZoneUnderAllExceptScopeWarnsWithNoExcludeHint(): void
    {
        $this->setScope('ALL_EXCEPT_SELECTED_ZONES');
        $this->setZones('GHOST_ZONE');
        $this->zoneRegistry->method('getByCode')->willReturn(null);
        $this->logger->expects($this->once())->method('warning')
            ->with($this->stringContains('unknown zone "GHOST_ZONE" (does not exclude)'));

        $this->assertSame(['GHOST_ZONE'], $this->reader->getAllowedZoneCodes('secomm_ghn'));
    }

    public function testDisabledZoneUnderAllExceptScopeWarnsWithNoExcludeHint(): void
    {
        $this->setScope('ALL_EXCEPT_SELECTED_ZONES');
        $this->setZones('HCM_INNER');
        $disabled = $this->createMock(CanonicalZoneInterface::class);
        $disabled->method('isEnabled')->willReturn(false);
        $this->zoneRegistry->method('getByCode')->willReturn($disabled);
        $this->logger->expects($this->once())->method('warning')
            ->with($this->stringContains('disabled zone "HCM_INNER" (not matching — does not exclude)'));

        $this->assertSame(['HCM_INNER'], $this->reader->getAllowedZoneCodes('secomm_ghn'));
    }

    public function testEnabledZoneUnderAllExceptScopeIsSilent(): void
    {
        $this->setScope('ALL_EXCEPT_SELECTED_ZONES');
        $this->setZones('HCM_INNER');
        $enabled = $this->createMock(CanonicalZoneInterface::class);
        $enabled->method('isEnabled')->willReturn(true);
        $this->zoneRegistry->method('getByCode')->willReturn($enabled);
        $this->logger->expects($this->never())->method('warning');

        $this->assertSame(['HCM_INNER'], $this->reader->getAllowedZoneCodes('secomm_ghn'));
    }

    public function testNoDiagnosticsUnderAllExceptScopeForEmptyList(): void
    {
        $this->setScope('ALL_EXCEPT_SELECTED_ZONES');
        $this->setZones('');
        $this->zoneRegistry->expects($this->never())->method('getByCode');

        $this->assertSame([], $this->reader->getAllowedZoneCodes('secomm_ghn'));
    }

    /**
     * Integration review §18.K — coverage resolves through SCOPE_STORE + the rate-request
     * storeId (Magento single-parent fallback store → website → default), proving
     * WEBSITE/STORE-level persisted values are live at runtime.
     */
    public function testStoreScopedCoverageReadUsesStoreScopeWithStoreId(): void
    {
        $captured = [];
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            function (string $path, ?string $scopeType = null, $scopeId = null) use (&$captured): ?string {
                $captured[] = [$path, $scopeType, $scopeId];

                return $scopeType === \Magento\Store\Model\ScopeInterface::SCOPE_STORE && $scopeId === 5
                    ? 'SELECTED_ZONES'
                    : null;
            }
        );
        $reader = new CarrierDestinationScopeConfig($scopeConfig, $this->zoneRegistry, $this->logger);

        $this->assertSame('SELECTED_ZONES', $reader->getDestinationScope('secomm_ghn', 5));
        $reader->getAllowedZoneCodes('secomm_ghn', 5);

        $storeScoped = [
            ['carriers/secomm_ghn/destination_scope', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, 5],
            ['carriers/secomm_ghn/allowed_zone_codes', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, 5],
        ];
        foreach ($storeScoped as $expected) {
            $this->assertContains($expected, $captured);
        }
    }

    /** No storeId → context-less read through SCOPE_TYPE_DEFAULT (documented behavior). */
    public function testContextLessReadUsesDefaultScope(): void
    {
        $captured = [];
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            function (string $path, ?string $scopeType = null, $scopeId = null) use (&$captured): ?string {
                $captured[] = [$path, $scopeType, $scopeId];

                return $scopeType === ScopeConfigInterface::SCOPE_TYPE_DEFAULT ? 'ALL' : null;
            }
        );
        $reader = new CarrierDestinationScopeConfig($scopeConfig, $this->zoneRegistry, $this->logger);

        $this->assertSame('ALL', $reader->getDestinationScope('secomm_ghn', null));
        $this->assertContains(
            ['carriers/secomm_ghn/destination_scope', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null],
            $captured
        );
    }
}
