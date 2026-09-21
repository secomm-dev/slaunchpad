<?php
/*
 * TASK-JZXM66 — importer template-column contract + region/city consistency validation.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Test\Unit\Model;

use Launchpad\MageplazaTableRate\Model\Adminhtml\SettingsPersister;
use Launchpad\MageplazaTableRate\Model\MptablerateImport;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\State;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\ResourceModel\Import\Data;
use Mageplaza\TableRateShipping\Helper\Data as TableRateHelper;
use Mageplaza\TableRateShipping\Model\RateFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;

class MptablerateImportTest extends TestCase
{
    private SettingsPersister|MockObject $persister;

    private MptablerateImport $import;

    protected function setUp(): void
    {
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $context->method('getAppState')->willReturn($this->createMock(State::class));
        $context->method('getEventDispatcher')->willReturn($this->createMock(ManagerInterface::class));
        $context->method('getCacheManager')->willReturn($this->createMock(CacheInterface::class));
        $context->method('getLogger')->willReturn($this->createMock(LoggerInterface::class));
        $context->method('getActionValidator')->willReturn(
            $this->createMock(\Magento\Framework\Model\ActionValidator\RemoveAction::class)
        );

        $this->persister = $this->createMock(SettingsPersister::class);

        // Mageplaza models: disableOriginalConstructor is mandatory; the tested paths never
        // touch the import data source / rate factory / region factory / helper.
        $this->import = new MptablerateImport(
            $context,
            new \Magento\Framework\Registry(),
            $this->createMock(Data::class),
            $this->createMock(Import::class),
            $this->createMock(RateFactory::class),
            $this->createMock(RegionFactory::class),
            $this->createMock(TableRateHelper::class),
            $this->persister
        );
    }

    public function testTemplateColumnsIsImporterSupersetPlusCityName(): void
    {
        $property = new ReflectionProperty(MptablerateImport::class, '_columnNames');
        $property->setAccessible(true);

        $this->assertSame(
            array_merge($property->getValue($this->import), ['city_name']),
            MptablerateImport::templateColumns()
        );
        $this->assertSame('city_code', $property->getValue($this->import)[18] ?? '');
        $this->assertSame('city_name', MptablerateImport::templateColumns()[19] ?? '');
    }

    public function testValidateCityRegionAcceptsMatchingRegion(): void
    {
        $this->persister->method('cityRegionId')->with('VNA25-X')->willReturn(20);

        $this->assertNull($this->import->validateCityRegion('VNA25-X', '20', 'Rate A'));
    }

    public function testValidateCityRegionRejectsMismatchWithRowLabel(): void
    {
        $this->persister->method('cityRegionId')->with('VNA25-X')->willReturn(99);

        $error = $this->import->validateCityRegion('VNA25-X', '20', 'Rate A');

        $this->assertNotNull($error);
        $this->assertStringContainsString('Rate A', (string) $error);
        $this->assertStringContainsString('VNA25-X', (string) $error);
        $this->assertStringContainsString('20', (string) $error);
    }

    public function testValidateCityRegionRejectsMismatchWithoutRowLabel(): void
    {
        $this->persister->method('cityRegionId')->willReturn(null);

        $error = $this->import->validateCityRegion('VNA25-X', '20');

        $this->assertNotNull($error);
        $this->assertStringNotContainsString('Row', (string) $error);
        $this->assertStringContainsString('VNA25-X', (string) $error);
    }

    /**
     * Legacy compatibility: wildcard region, non-numeric region (unresolved region code) and
     * wildcard city never trigger the check — no provider call, no error.
     *
     * @dataProvider wildcardAndLegacyProvider
     */
    public function testValidateCityRegionSkipsWildcardAndLegacyRows(string $cityCode, string $region): void
    {
        $this->persister->expects($this->never())->method('cityRegionId');

        $this->assertNull($this->import->validateCityRegion($cityCode, $region, 'Rate A'));
    }

    public static function wildcardAndLegacyProvider(): array
    {
        return [
            'wildcard city' => ['', '20'],
            'wildcard region' => ['VNA25-X', '*'],
            'empty region' => ['VNA25-X', ''],
            'region code (non numeric)' => ['VNA25-X', 'binh-duong'],
            'zero region' => ['VNA25-X', '0'],
        ];
    }
}
