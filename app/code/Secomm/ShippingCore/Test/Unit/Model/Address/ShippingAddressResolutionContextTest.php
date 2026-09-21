<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Address;

use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\Address\ShippingAddressResolutionContextInterface;
use Secomm\ShippingCore\Model\Address\ShippingAddressResolutionContext;

/**
 * TASK-AQT7V3 — scalar-only resolution context transport.
 * TASK-5XDG1P TL review — receiver identity is NOT part of the contract (PII hygiene).
 */
class ShippingAddressResolutionContextTest extends TestCase
{
    public function testCarriesDisambiguationInputs(): void
    {
        $candidates = ['VNAP25-B2B2B2B2B2', 'VNAP25-A1A1A1A1A1'];
        $context = new ShippingAddressResolutionContext(
            countryId: 'VN',
            sourceScheme: 'VN_ADMIN_2025',
            sourceUnitCode: 'VNA25-0A1B2C3D4E',
            targetScheme: 'VN_ADMIN_PRE_2025',
            streetText: '12 Nguyễn Huệ, phường Bến Nghé',
            candidateCodes: $candidates
        );

        $this->assertSame('VN', $context->getCountryId());
        $this->assertSame('VN_ADMIN_2025', $context->getSourceScheme());
        $this->assertSame('VNA25-0A1B2C3D4E', $context->getSourceUnitCode());
        $this->assertSame('VN_ADMIN_PRE_2025', $context->getTargetScheme());
        $this->assertSame('12 Nguyễn Huệ, phường Bến Nghé', $context->getStreetText());
        $this->assertSame($candidates, $context->getCandidateCodes());
    }

    public function testAllOptionalFieldsDefaultToNullAndEmpty(): void
    {
        $context = new ShippingAddressResolutionContext(
            countryId: null,
            sourceScheme: null,
            sourceUnitCode: null,
            targetScheme: 'VN_ADMIN_2025'
        );

        $this->assertNull($context->getCountryId());
        $this->assertNull($context->getSourceScheme());
        $this->assertNull($context->getSourceUnitCode());
        $this->assertNull($context->getStreetText());
        $this->assertSame([], $context->getCandidateCodes());
    }

    public function testReceiverIdentityIsNotPartOfTheContextContract(): void
    {
        // TASK-5XDG1P TL review regression guard: no recipient identity accessor may return to
        // the resolution context (plain method_exists — no reflection-heavy machinery).
        $this->assertFalse(
            method_exists(ShippingAddressResolutionContextInterface::class, 'getReceiverText')
        );
        $this->assertFalse(method_exists(ShippingAddressResolutionContext::class, 'getReceiverText'));
        $this->assertFalse(method_exists(ShippingAddressResolutionContext::class, 'getCustomerName'));
        // Address-related text stays available for future external disambiguation.
        $this->assertTrue(method_exists(ShippingAddressResolutionContextInterface::class, 'getStreetText'));
    }

    public function testRejectsEmptyTargetScheme(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('non-empty target scheme');
        new ShippingAddressResolutionContext(
            countryId: 'VN',
            sourceScheme: null,
            sourceUnitCode: null,
            targetScheme: ''
        );
    }

    public function testRejectsNonStringCandidateCode(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('non-empty strings');
        new ShippingAddressResolutionContext(
            countryId: 'VN',
            sourceScheme: null,
            sourceUnitCode: null,
            targetScheme: 'VN_ADMIN_PRE_2025',
            candidateCodes: [21511]
        );
    }
}
