<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\ServiceLevel;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Model\ServiceLevel\ShippingServiceLevel;
use Secomm\ShippingCore\Model\ServiceLevel\ShippingServiceLevelRegistry;

/**
 * TASK-XXBN5X r1 — DYNAMIC service-level registry: zero-level valid, dynamic lookup/validation,
 * no hardcoded taxonomy anywhere.
 */
class ShippingServiceLevelRegistryTest extends TestCase
{
    public function testZeroRegisteredLevelsIsValidState(): void
    {
        $registry = new ShippingServiceLevelRegistry([]);

        $this->assertSame([], $registry->getAll());
        $this->assertSame([], $registry->getEnabled());
        $this->assertFalse($registry->has('STANDARD'));
        $this->assertNull($registry->getByCode('STANDARD'));
    }

    public function testSingleLevelIsDiscoverable(): void
    {
        $standard = new ShippingServiceLevel('STANDARD', 'Giao tiêu chuẩn');
        $registry = new ShippingServiceLevelRegistry([$standard]);

        $this->assertTrue($registry->has('STANDARD'));
        $this->assertSame($standard, $registry->getByCode('STANDARD'));
        $this->assertSame([$standard], $registry->getEnabled());
    }

    public function testMultipleLevelsPreserveRegistrationOrder(): void
    {
        $express = new ShippingServiceLevel('EXPRESS', 'Giao nhanh 2 giờ', true, 10);
        $standard = new ShippingServiceLevel('STANDARD', 'Giao tiêu chuẩn', true, 30);
        $registry = new ShippingServiceLevelRegistry([$express, $standard]);

        $this->assertSame([$express, $standard], $registry->getAll());
    }

    public function testLabelsAreIndependentFromMachineCodes(): void
    {
        // The code survives a label change — the identity is the machine code, not presentation.
        $before = new ShippingServiceLevel('EXPRESS', 'Giao nhanh 2 giờ');
        $after = new ShippingServiceLevel('EXPRESS', 'Giao hỏa tốc trong 90 phút');
        $registry = new ShippingServiceLevelRegistry([$before]);

        $this->assertSame('EXPRESS', $after->getCode());
        $this->assertSame($before, $registry->getByCode('EXPRESS'));
        $this->assertNotSame($before->getLabel(), $after->getLabel());
    }

    public function testGetEnabledFiltersAndSortsBySortOrderStably(): void
    {
        $standard = new ShippingServiceLevel('STANDARD', 'Standard', true, 30);
        $sameDay = new ShippingServiceLevel('SAME_DAY', 'Same day', true, 20);
        $express = new ShippingServiceLevel('EXPRESS', 'Express', true, 10);
        $retired = new ShippingServiceLevel('NEXT_DAY', 'Next day', false, 5);
        $registry = new ShippingServiceLevelRegistry([$standard, $sameDay, $express, $retired]);

        $this->assertSame([$express, $sameDay, $standard], $registry->getEnabled());
    }

    public function testEqualSortOrderKeepsRegistrationOrder(): void
    {
        $first = new ShippingServiceLevel('A_FIRST', 'First', true, 10);
        $second = new ShippingServiceLevel('B_SECOND', 'Second', true, 10);
        $registry = new ShippingServiceLevelRegistry([$first, $second]);

        $this->assertSame([$first, $second], $registry->getEnabled());
    }

    public function testAssertKnownAcceptsRegisteredCode(): void
    {
        $registry = new ShippingServiceLevelRegistry([new ShippingServiceLevel('STANDARD', 'Standard')]);

        $registry->assertKnown('STANDARD');
        $this->addToAssertionCount(1);
    }

    public function testAssertKnownRejectsUnknownCodeAsConfigurationError(): void
    {
        $registry = new ShippingServiceLevelRegistry([new ShippingServiceLevel('STANDARD', 'Standard')]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Unknown shipping service level code');
        $registry->assertKnown('INSTANT');
    }

    public function testRejectsDuplicateMachineCode(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Duplicate shipping service level code');
        new ShippingServiceLevelRegistry([
            new ShippingServiceLevel('STANDARD', 'Standard'),
            new ShippingServiceLevel('STANDARD', 'Standard duplicate'),
        ]);
    }

    public function testRejectsEmptyMachineCodeInRegistration(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('non-empty machine code');
        new ShippingServiceLevelRegistry([new ShippingServiceLevel('', 'Broken')]);
    }
}
