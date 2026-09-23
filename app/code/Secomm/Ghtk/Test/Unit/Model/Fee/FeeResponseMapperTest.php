<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Fee;

use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Fee\FeeResponseMapper;
use Secomm\Ghtk\Model\Rate\GhtkFeeResponse;

/**
 * TASK-W8SH0N — the RATE parser distinguishes the three contract shapes:
 * SUCCESS payload / BUSINESS_REJECTION (HTTP 200 + success=false) / MALFORMED
 * (structurally unusable — classified TECHNICAL_FAILURE downstream). §33 matrix.
 */
class FeeResponseMapperTest extends TestCase
{
    private FeeResponseMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new FeeResponseMapper();
    }

    public function testParsesValidSuccessFeeBlock(): void
    {
        $parsed = $this->mapper->parse([
            'success' => true,
            'fee' => ['fee' => 30000, 'insurance_fee' => 5000, 'extFees' => 0, 'delivery' => true, 'name' => 'area1'],
        ]);

        $this->assertSame(GhtkFeeResponse::KIND_SUCCESS, $parsed->getKind());
        $fee = $parsed->getFee();
        $this->assertNotNull($fee);
        $this->assertSame(30000.0, $fee->fee);
        $this->assertSame(5000.0, $fee->insuranceFee);
        $this->assertTrue($fee->delivery);
        $this->assertSame('area1', $fee->name);
    }

    public function testParsesBusinessRejectionWithErrorCode(): void
    {
        $parsed = $this->mapper->parse([
            'success' => false,
            'error_code' => 'INVALID_ADDRESS',
            'message' => 'Địa chỉ không hợp lệ',
        ]);

        $this->assertSame(GhtkFeeResponse::KIND_BUSINESS_REJECTION, $parsed->getKind());
        $this->assertSame('INVALID_ADDRESS', $parsed->getErrorCode());
        $this->assertSame('Địa chỉ không hợp lệ', $parsed->getMessage());
        $this->assertNull($parsed->getFee());
    }

    public function testParsesBusinessRejectionWithoutErrorCode(): void
    {
        $parsed = $this->mapper->parse(['success' => false, 'message' => 'Lỗi']);

        $this->assertSame(GhtkFeeResponse::KIND_BUSINESS_REJECTION, $parsed->getKind());
        $this->assertNull($parsed->getErrorCode());
    }

    public function testMissingFeeBlockIsMalformedNotBusiness(): void
    {
        $parsed = $this->mapper->parse(['success' => true, 'message' => 'ok']);

        $this->assertSame(GhtkFeeResponse::KIND_MALFORMED, $parsed->getKind());
    }

    public function testNonNumericFeeAmountIsMalformed(): void
    {
        // §11 — a usable rate requires a numeric amount; without it the payload is
        // structurally broken (TECHNICAL downstream), never a silent 0-fee success.
        $parsed = $this->mapper->parse(['success' => true, 'fee' => ['delivery' => true]]);

        $this->assertSame(GhtkFeeResponse::KIND_MALFORMED, $parsed->getKind());
    }

    public function testDeliveryFalseWithoutAmountIsBusinessRejection(): void
    {
        // The documented business answer (unsupported destination) needs no amount.
        $parsed = $this->mapper->parse(['success' => true, 'fee' => ['delivery' => false]]);

        $this->assertSame(GhtkFeeResponse::KIND_BUSINESS_REJECTION, $parsed->getKind());
    }

    public function testDeliveryMissingDefaultsToDenied(): void
    {
        $parsed = $this->mapper->parse([
            'success' => true,
            'fee' => ['fee' => 30000, 'insurance_fee' => 0, 'extFees' => 0],
        ]);

        $this->assertSame(GhtkFeeResponse::KIND_SUCCESS, $parsed->getKind());
        $this->assertFalse($parsed->getFee()?->delivery); // safe default — no rate shown
    }

    public function testExtFeesAbsentDefaultsToZero(): void
    {
        $parsed = $this->mapper->parse([
            'success' => true,
            'fee' => ['fee' => 25000, 'delivery' => true],
        ]);

        $this->assertSame(GhtkFeeResponse::KIND_SUCCESS, $parsed->getKind());
        $this->assertSame(0.0, $parsed->getFee()?->extFees);
        $this->assertSame(0.0, $parsed->getFee()?->insuranceFee);
    }
}
