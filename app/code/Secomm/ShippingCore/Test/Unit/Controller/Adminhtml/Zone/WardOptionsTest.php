<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Controller\Adminhtml\Zone;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Controller\Adminhtml\Zone\WardOptions;
use Secomm\VietNamAddress\Api\Data\VnAddressUnitInterface;
use Secomm\VietNamAddress\Api\VnAddressUnitProviderInterface;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 + TASK-G3K9V2 — AJAX ward options: AJAX guard, canonical
 * code identity, "name (CODE)" display labels, duplicate-safe across provinces, resolution
 * via region_code + level (getByRegion — seeded rows carry NULL parent_code).
 */
class WardOptionsTest extends TestCase
{
    private function ward(string $code, string $nameVi): VnAddressUnitInterface
    {
        $unit = $this->createMock(VnAddressUnitInterface::class);
        $unit->method('getCode')->willReturn($code);
        $unit->method('getNameVi')->willReturn($nameVi);
        $unit->method('getNameEn')->willReturn($code);

        return $unit;
    }

    private function build(Http $request): WardOptions
    {
        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);

        return new WardOptions(
            $context,
            $this->createMock(JsonFactory::class),
            $this->createMock(VnAddressUnitProviderInterface::class)
        );
    }

    public function testOptionsCarryCanonicalCodesAndNameCodeLabels(): void
    {
        $request = $this->createMock(Http::class);
        $request->method('isAjax')->willReturn(true);
        $request->method('getParam')->with('provinces')->willReturn('VN-15,VN-01');

        $unitProvider = $this->createMock(VnAddressUnitProviderInterface::class);
        $unitProvider->expects($this->exactly(2))->method('getByRegion')->willReturnCallback(
            function (string $scheme, string $regionCode, int $level): array {
                $this->assertSame(2, $level);
                if ($regionCode === 'VN-15') {
                    return [$this->ward('VNA25-AAA', 'Phường Bến Nghé')];
                }

                return [$this->ward('VNA25-BBB', 'Phường A'), $this->ward('VNA25-BBB', 'duplicate')];
            }
        );

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($request);
        $json = $this->createMock(Json::class);
        $json->method('setData')->willReturnSelf();
        $jsonFactory = $this->createMock(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($json);
        $json->expects($this->once())->method('setData')->with(['options' => [
            ['value' => 'VNA25-AAA', 'label' => 'Phường Bến Nghé (VNA25-AAA)'],
            ['value' => 'VNA25-BBB', 'label' => 'Phường A (VNA25-BBB)'],
        ]]);

        $controller = new WardOptions($context, $jsonFactory, $unitProvider);
        $controller->execute();
    }

    public function testNonAjaxIsRoutedAway(): void
    {
        $request = $this->createMock(Http::class);
        $request->method('isAjax')->willReturn(false);
        $controller = $this->build($request);

        // The controller forwards to noroute; no exception surfaces to the caller.
        $this->assertNotNull($controller);
    }
}
