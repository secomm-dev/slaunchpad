<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Shipment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Config;
use Secomm\Ghn\Model\GhnShipmentConstraints;
use Secomm\Ghn\Model\Shipment\GhnCreateParcelValidator;
use Secomm\Ghn\Model\Shipment\GhnCreateValidationException;
use Secomm\Ghn\Model\Shipment\GhnPhysicalParcelInterpreter;
use Secomm\Ghn\Model\Shipment\GhnPhysicalLimit;
use Secomm\ShippingCore\Model\Physical\PhysicalPackage;
use Secomm\ShippingCore\Model\Physical\ShipmentPhysicalData;
use Secomm\ShippingCore\Model\Physical\StoreWeightConverter;

/**
 * TASK-W5BW4F — the shared deterministic parcel gate: row usability (missing/zero fields),
 * GHN per-package hard limits, and the canonical missing-data message. The creation service
 * and the pre-save validation observer both consume THIS class, so the two gates cannot drift.
 */
class GhnCreateParcelValidatorTest extends TestCase
{
    private GhnCreateParcelValidator $validator;

    protected function setUp(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('kgs');
        $limitConfig = $this->createMock(Config::class);
        $limitConfig->method('getMaxLengthCm')->willReturn(GhnShipmentConstraints::MAX_SIDE_CM);
        $limitConfig->method('getMaxWidthCm')->willReturn(GhnShipmentConstraints::MAX_SIDE_CM);
        $limitConfig->method('getMaxHeightCm')->willReturn(GhnShipmentConstraints::MAX_SIDE_CM);
        $this->validator = new GhnCreateParcelValidator(
            new GhnPhysicalParcelInterpreter(new GhnPhysicalLimit($limitConfig)),
            new StoreWeightConverter($scopeConfig)
        );
    }

    public function testValidRowsBuildConfirmedFacts(): void
    {
        $physical = $this->validator->fromPostedRows(
            [
                ['weight' => 48.6, 'length' => 60, 'width' => 50, 'height' => 40],
                ['weight' => 1.5, 'length' => 30, 'width' => 20, 'height' => 10],
            ],
            null
        );

        $this->assertCount(2, $physical->getPackages());
        $this->assertSame(48600, $physical->getPackages()[0]->getWeightG());
        $this->assertSame(1500, $physical->getPackages()[1]->getWeightG());
    }

    public function testZeroFieldRowIsRejectedWithThePackageNumber(): void
    {
        $this->expectException(GhnCreateValidationException::class);
        $this->expectExceptionMessage('package #2 has an empty weight or dimension');

        $this->validator->fromPostedRows(
            [
                ['weight' => 1, 'length' => 10, 'width' => 10, 'height' => 10],
                ['weight' => 2, 'length' => 0, 'width' => 10, 'height' => 10],
            ],
            null
        );
    }

    public function testLimitViolationIsRejected(): void
    {
        $physical = ShipmentPhysicalData::fromPackages([new PhysicalPackage(48600, 300, 300, 300)]);

        $this->expectException(GhnCreateValidationException::class);
        $this->expectExceptionMessage('above the 200 cm length limit');

        $this->validator->assertValid($physical);
    }

    public function testValidPackagesPassTheLimitsGate(): void
    {
        $physical = ShipmentPhysicalData::fromPackages([new PhysicalPackage(48600, 60, 50, 40)]);

        $this->validator->assertValid($physical);
        $this->addToAssertionCount(1); // no exception = pass
    }

    public function testMissingParcelMessageIsTheCanonicalFailClosedText(): void
    {
        $this->assertSame(
            'GHN create: no confirmed package weight/dimensions on the shipment. '
            . 'Enter the real package information when creating the shipment.',
            (string) GhnCreateParcelValidator::missingParcelMessage()
        );
    }
}
