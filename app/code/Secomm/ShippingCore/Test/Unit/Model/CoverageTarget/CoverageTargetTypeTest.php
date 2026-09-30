<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\CoverageTarget;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetType;

/**
 * TASK-WY6WP5 — target type contract: CARRIER is the implemented P1 type; METHOD is a
 * reserved constant with no P1 surface (directive §2/§23 — no method runtime).
 */
class CoverageTargetTypeTest extends TestCase
{
    public function testAllTypesKnown(): void
    {
        $this->assertSame([CoverageTargetType::CARRIER, CoverageTargetType::METHOD], CoverageTargetType::all());
    }

    public function testExistsAcceptsOnlyKnownTypes(): void
    {
        $this->assertTrue(CoverageTargetType::exists(CoverageTargetType::CARRIER));
        $this->assertTrue(CoverageTargetType::exists(CoverageTargetType::METHOD));
        $this->assertFalse(CoverageTargetType::exists('WIDGET'));
        $this->assertFalse(CoverageTargetType::exists(''));
    }

    public function testOnlyCarrierIsImplementedInP1(): void
    {
        $this->assertTrue(CoverageTargetType::isImplemented(CoverageTargetType::CARRIER));
        // Reserved: no registry producer, no admin surface, no runtime consumer in P1.
        $this->assertFalse(CoverageTargetType::isImplemented(CoverageTargetType::METHOD));
    }
}
