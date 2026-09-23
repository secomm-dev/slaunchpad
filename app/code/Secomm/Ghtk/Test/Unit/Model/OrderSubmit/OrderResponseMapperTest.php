<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\OrderSubmit;

use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\OrderSubmit\GhtkCreateResponse;
use Secomm\Ghtk\Model\OrderSubmit\OrderResponseMapper;

/**
 * TASK-BE5YD2 — CREATE response parser: four typed kinds. Identity
 * normalization yields the same shape for normal success (label/tracking_id)
 * and duplicate recovery (partner_id/ghtk_label).
 */
class OrderResponseMapperTest extends TestCase
{
    private OrderResponseMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new OrderResponseMapper();
    }

    // ------------------------------------------------------------ CREATED

    public function testSuccessParsesCreatedWithOfficialFields(): void
    {
        $parsed = $this->mapper->parse([
            'success' => true,
            'order' => [
                'partner_id' => 'ghtk-100000001-1',
                'label' => 'S1.A1.2001297581',
                'tracking_id' => 'S10001.P1.XXXX',
                'status_id' => 1,
                'fee' => 30000,
            ],
        ]);

        $this->assertSame(GhtkCreateResponse::KIND_CREATED, $parsed->getKind());
        $this->assertSame('ghtk-100000001-1', $parsed->getPartnerId());
        $this->assertSame('S1.A1.2001297581', $parsed->getLabel());
        $this->assertSame('S10001.P1.XXXX', $parsed->getTrackingNumber());
        $this->assertSame('1', $parsed->getProviderStatus());
        $this->assertTrue($parsed->hasUsableIdentity());
    }

    public function testTrackingIdIsPreferredOverLegacyCandidates(): void
    {
        $parsed = $this->mapper->parse([
            'success' => true,
            'order' => ['label' => 'S1.A1.LABEL', 'tracking_code' => 'TC', 'tracking' => 'T', 'tracking_id' => 'S1.A1.TRACKING'],
        ]);

        $this->assertSame('S1.A1.TRACKING', $parsed->getTrackingNumber());
        $this->assertSame('S1.A1.LABEL', $parsed->getLabel());
    }

    public function testLabelFallbackWhenNoTrackingField(): void
    {
        $parsed = $this->mapper->parse(['success' => true, 'order' => ['label' => 'S10002.P1']]);

        $this->assertSame(GhtkCreateResponse::KIND_CREATED, $parsed->getKind());
        $this->assertSame('S10002.P1', $parsed->getTrackingNumber());
    }

    public function testNumericLabelIsCast(): void
    {
        $parsed = $this->mapper->parse(['success' => true, 'order' => ['label_id' => 123456]]);

        $this->assertSame('123456', $parsed->getLabel());
    }

    // ------------------------------------------------------------ DUPLICATE_EXISTING

    public function testOrderIdExistParsesRecoveryIdentityFromTopLevel(): void
    {
        $parsed = $this->mapper->parse([
            'success' => false,
            'error_code' => 'ORDER_ID_EXIST',
            'partner_id' => '1234567',
            'ghtk_label' => 'S1.A1.17373471',
            'created' => '2016-11-02T12:18:39+07:00',
            'status' => 1,
        ]);

        $this->assertSame(GhtkCreateResponse::KIND_DUPLICATE_EXISTING, $parsed->getKind());
        $this->assertSame('1234567', $parsed->getPartnerId());
        $this->assertSame('S1.A1.17373471', $parsed->getLabel());
        $this->assertSame('S1.A1.17373471', $parsed->getTrackingNumber()); // ghtk_label primary (§20)
        $this->assertSame('1', $parsed->getProviderStatus());
        $this->assertSame('ORDER_ID_EXIST', $parsed->getErrorCode());
    }

    public function testOrderIdExistIdentityAlsoParsedFromOrderBlock(): void
    {
        // Exact runtime position NEEDS_RUNTIME_VERIFICATION — parser accepts both.
        $parsed = $this->mapper->parse([
            'success' => false,
            'error_code' => 'ORDER_ID_EXIST',
            'order' => ['partner_id' => '1234567', 'ghtk_label' => 'S1.A1.17373471'],
        ]);

        $this->assertSame(GhtkCreateResponse::KIND_DUPLICATE_EXISTING, $parsed->getKind());
        $this->assertSame('1234567', $parsed->getPartnerId());
        $this->assertSame('S1.A1.17373471', $parsed->getLabel());
    }

    public function testOtherErrorCodesAreBusinessRejections(): void
    {
        $parsed = $this->mapper->parse([
            'success' => false,
            'error_code' => 'INVALID_ADDRESS',
            'message' => 'Địa chỉ không hợp lệ',
        ]);

        $this->assertSame(GhtkCreateResponse::KIND_BUSINESS_REJECTION, $parsed->getKind());
        $this->assertSame('INVALID_ADDRESS', $parsed->getErrorCode());
        $this->assertSame('Địa chỉ không hợp lệ', $parsed->getMessage());
    }

    // ------------------------------------------------------------ MALFORMED

    public function testSuccessWithoutUsableIdentityIsMalformed(): void
    {
        // §18 — no shipment may be built from an identity-less success.
        $parsed = $this->mapper->parse(['success' => true, 'order' => ['partner_id' => 'P1']]);

        $this->assertSame(GhtkCreateResponse::KIND_MALFORMED, $parsed->getKind());
        $this->assertFalse($parsed->hasUsableIdentity());
    }

    public function testMissingSuccessContractIsMalformed(): void
    {
        $parsed = $this->mapper->parse(['message' => 'no contract']);

        $this->assertSame(GhtkCreateResponse::KIND_MALFORMED, $parsed->getKind());
    }

    public function testGarbageResponseIsMalformed(): void
    {
        $parsed = $this->mapper->parse([]);

        $this->assertSame(GhtkCreateResponse::KIND_MALFORMED, $parsed->getKind());
        $this->assertFalse($parsed->hasUsableIdentity());
    }
}
