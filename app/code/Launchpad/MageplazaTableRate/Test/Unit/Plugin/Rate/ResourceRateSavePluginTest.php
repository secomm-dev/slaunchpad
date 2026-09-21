<?php
/*
 * TASK-JZXM66 (TL review round) — no partial-save semantics: City/Area validation happens
 * BEFORE Mageplaza rate persistence; the constraint row is persisted only after a valid save.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Test\Unit\Plugin\Rate;

use Launchpad\MageplazaTableRate\Model\Adminhtml\SettingsPersister;
use Launchpad\MageplazaTableRate\Plugin\Rate\ResourceRateSavePlugin;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Mageplaza\TableRateShipping\Model\Rate;
use Mageplaza\TableRateShipping\Model\ResourceModel\Rate as RateResource;
use PHPUnit\Framework\TestCase;

class ResourceRateSavePluginTest extends TestCase
{
    private SettingsPersister $persister;

    private ResourceRateSavePlugin $plugin;

    private Rate $rate;

    private RateResource $resource;

    protected function setUp(): void
    {
        $this->persister = $this->getMockBuilder(SettingsPersister::class)
            ->disableOriginalConstructor()->getMock();
        $this->plugin = new ResourceRateSavePlugin($this->persister);
        $this->resource = $this->createMock(RateResource::class);
        // getId/getRegion/setCity are real or magic — only magic accessors used below.
        $this->rate = $this->getMockBuilder(Rate::class)
            ->addMethods(['getRegion'])
            ->onlyMethods(['getId'])
            ->disableOriginalConstructor()->getMock();
        $this->rate->method('getId')->willReturn(7);
    }

    private function rateWith(array $data): AbstractModel
    {
        $this->rate->setData($data);

        return $this->rate;
    }

    public function testMismatchedRegionAbortsBeforeSave(): void
    {
        $this->rate->setData(['city_code' => 'VNA25-X', 'region' => '1205']);
        $this->persister->method('cityCodeExists')->willReturn(true);
        $this->persister->method('cityBelongsToRegion')->willReturn(false);
        $this->persister->expects($this->never())->method('saveRateCity');

        try {
            $this->plugin->beforeSave($this->resource, $this->rate);
            $this->fail('Expected LocalizedException');
        } catch (LocalizedException) {
            // expected — the rate row must NOT have been persisted
        }
    }

    public function testUnknownCodeAbortsBeforeSave(): void
    {
        $this->rate->setData(['city_code' => 'VNA25-GONE', 'region' => '*']);
        $this->persister->method('cityCodeExists')->willReturn(false);
        $this->persister->expects($this->never())->method('saveRateCity');

        $this->expectException(LocalizedException::class);
        $this->plugin->beforeSave($this->resource, $this->rate);
    }

    public function testValidCityPassesValidationThenPersists(): void
    {
        $this->rate->setData(['city_code' => 'VNA25-X', 'region' => '1205']);
        $this->persister->method('cityCodeExists')->willReturn(true);
        $this->persister->method('cityBelongsToRegion')->willReturn(true);
        $this->persister->expects($this->once())->method('saveRateCity')->with(7, 'VNA25-X');

        $this->plugin->beforeSave($this->resource, $this->rate);
        $this->plugin->afterSave($this->resource, $this->rate, $this->rate);
    }

    public function testWildcardCitySkipsValidationAndRemovesConstraint(): void
    {
        $this->rate->setData(['city_code' => '', 'region' => '*']);
        $this->persister->expects($this->never())->method('cityCodeExists');
        $this->persister->expects($this->once())->method('saveRateCity')->with(7, '');

        $this->plugin->beforeSave($this->resource, $this->rate);
        $this->plugin->afterSave($this->resource, $this->rate, $this->rate);
    }

    public function testNoCityKeyIsInert(): void
    {
        $this->rate->setData(['region' => '1205']);
        $this->persister->expects($this->never())->method('cityCodeExists');
        $this->persister->expects($this->never())->method('saveRateCity');

        $this->plugin->beforeSave($this->resource, $this->rate);
        $this->plugin->afterSave($this->resource, $this->rate, $this->rate);
    }
}
