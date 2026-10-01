<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Shipment;

use Magento\Sales\Api\Data\OrderAddressInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Shipment\GhnCreateRequestBuilder;
use Secomm\Ghn\Model\Shipment\GhnCreateValidationException;
use Secomm\Ghn\Model\Shipment\GhnParcelPlan;

/**
 * TASK-9Q5ZAK r2 + TASK-DFGFZ9 phase 2 (DEC-TASKDFGFZ9-002) — the Create Order payload
 * contract: the interpreter's plan decides root fields vs items[]; config enums validated
 * fail-closed; `cod_amount` is ALWAYS emitted (the mapped Secomm_Cod decision — 0 for
 * non-COD, provider-capped above); every other architecture-mandated ABSENCE asserted
 * (no insurance/order value, no sender/return, no service_id).
 */
class GhnCreateRequestBuilderTest extends TestCase
{
    private GhnCreateRequestBuilder $builder;

    private OrderAddressInterface&MockObject $address;

    private string $telephone = '0901234567';

    protected function setUp(): void
    {
        $this->builder = new GhnCreateRequestBuilder();
        $this->address = $this->createMock(OrderAddressInterface::class);
        $this->address->method('getFirstname')->willReturn('  An  ');
        $this->address->method('getLastname')->willReturn('  Nguyễn  ');
        $telephone = &$this->telephone;
        $this->address->method('getTelephone')->willReturnCallback(function () use (&$telephone): string {
            return $telephone;
        });
        $this->address->method('getStreet')->willReturn(['12 Nguyễn Huệ', 'P. Bến Nghé']);
    }

    public function testType2PlanMapsThePhysicalPackageToRootFieldsWithContent(): void
    {
        $payload = $this->builder->build(
            'GHNS42',
            'Lạng Sơn',
            'Xã Tân Thanh',
            new GhnParcelPlan(2, 1500, 30, 20, 10, null),
            $this->address,
            1,
            'CHOXEMHANGKHONGTHU',
            'Waffle Blanket x1',
            125000
        );

        $this->assertSame([
            'client_order_code' => 'GHNS42',
            'to_name' => 'An Nguyễn',
            'to_phone' => '0901234567',
            'to_address' => '12 Nguyễn Huệ, P. Bến Nghé',
            'to_province_name' => 'Lạng Sơn',
            'to_ward_name' => 'Xã Tân Thanh',
            'to_district_name' => '',
            'is_new_to_address' => true,
            'service_type_id' => 2,
            'payment_type_id' => 1,
            'required_note' => 'CHOXEMHANGKHONGTHU',
            'cod_amount' => 125000,
            'weight' => 1500,
            'length' => 30,
            'width' => 20,
            'height' => 10,
            'content' => 'Waffle Blanket x1',
        ], $payload);
    }

    public function testType5PlanSendsItemsPerPhysicalPackageInsteadOfContent(): void
    {
        $items = [
            ['name' => 'Package 1', 'quantity' => 1, 'weight' => 45000, 'length' => 60, 'width' => 50, 'height' => 40],
            ['name' => 'Package 2', 'quantity' => 1, 'weight' => 1000, 'length' => 10, 'width' => 10, 'height' => 10],
        ];
        $payload = $this->builder->build(
            'GHNS42',
            'Lạng Sơn',
            'Xã Tân Thanh',
            new GhnParcelPlan(5, null, null, null, null, $items),
            $this->address,
            1,
            'KHONGCHOXEMHANG',
            'ignored when items present',
            0
        );

        $this->assertSame(5, $payload['service_type_id']);
        $this->assertSame(0, $payload['cod_amount'], 'non-COD order still emits cod_amount = 0 (provider default shape)');
        $this->assertSame($items, $payload['items']);
        $this->assertArrayNotHasKey('length', $payload, 'type-5 root dims omitted');
        $this->assertArrayNotHasKey('width', $payload);
        $this->assertArrayNotHasKey('height', $payload);
        $this->assertArrayNotHasKey('content', $payload, 'content is only required when items[] is absent');
    }

    public function testType5RootWeightMandatoryDimsOmitted(): void
    {
        $payload = $this->builder->build(
            'GHNS42',
            'Lạng Sơn',
            'Xã Tân Thanh',
            new GhnParcelPlan(5, 60000, null, null, null, [
                ['name' => 'Package 1', 'quantity' => 1, 'weight' => 30000, 'length' => 50, 'width' => 40, 'height' => 30],
                ['name' => 'Package 2', 'quantity' => 1, 'weight' => 30000, 'length' => 50, 'width' => 40, 'height' => 30],
            ]),
            $this->address,
            1,
            'KHONGCHOXEMHANG',
            'ignored',
            0
        );

        $this->assertSame(60000, $payload['weight'], 'root weight (factual Σ) is provider-mandatory');
        foreach (['length', 'width', 'height'] as $rootField) {
            $this->assertArrayNotHasKey($rootField, $payload, "type-5 must not send root $rootField");
        }
        $this->assertCount(2, $payload['items']);
    }

    public function testArchitectureMandatedFieldsAreNeverSent(): void
    {
        $payload = $this->builder->build(
            'GHNS1',
            'Lạng Sơn',
            'Xã Tân Thanh',
            new GhnParcelPlan(2, 1000, 10, 10, 10, null),
            $this->address,
            1,
            'KHONGCHOXEMHANG',
            'content',
            0
        );

        // TASK-DFGFZ9 phase 2: cod_amount is ALWAYS emitted (mapped Secomm_Cod decision; 0 = nothing to collect).
        $this->assertSame(0, $payload['cod_amount']);

        foreach ([
            'cod_failed_amount', 'insurance_value', 'order_value',
            'from_name', 'from_phone', 'from_address', 'from_ward_name',
            'from_district_name', 'from_province_name', 'return_name', 'return_phone',
            'return_address', 'return_district_name', 'service_id', 'coupon',
        ] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $payload, "$forbidden must never be sent");
        }
    }

    public function testInvalidPaymentTypeFailsClosedAsInvalidConfiguration(): void
    {
        $this->expectException(GhnCreateValidationException::class);
        $this->expectExceptionMessage('payment_type');

        $this->builder->build(
            'GHNS1',
            'Lạng Sơn',
            'Xã Tân Thanh',
            new GhnParcelPlan(2, 1000, 10, 10, 10, null),
            $this->address,
            3,
            'KHONGCHOXEMHANG',
            'content',
            0
        );
    }

    public function testInvalidRequiredNoteFailsClosedAsInvalidConfiguration(): void
    {
        try {
            $this->builder->build(
                'GHNS1',
                'Lạng Sơn',
                'Xã Tân Thanh',
                new GhnParcelPlan(2, 1000, 10, 10, 10, null),
                $this->address,
                1,
                'CHOXEMHANGDUOC',
                'content',
                0
            );
            $this->fail('Expected GhnCreateValidationException');
        } catch (GhnCreateValidationException $exception) {
            $this->assertSame(GhnCreateValidationException::REASON_INVALID_CONFIGURATION, $exception->getReasonToken());
        }
    }

    public function testCodAmountAboveProviderCapFailsClosed(): void
    {
        try {
            $this->builder->build(
                'GHNS1',
                'Lạng Sơn',
                'Xã Tân Thanh',
                new GhnParcelPlan(2, 1000, 10, 10, 10, null),
                $this->address,
                1,
                'KHONGCHOXEMHANG',
                'content',
                GhnCreateRequestBuilder::MAX_COD_AMOUNT + 1
            );
            $this->fail('Expected GhnCreateValidationException');
        } catch (GhnCreateValidationException $exception) {
            $this->assertSame(GhnCreateValidationException::REASON_INVALID_CONFIGURATION, $exception->getReasonToken());
        }
    }

    public function testNegativeCodAmountFailsClosed(): void
    {
        $this->expectException(GhnCreateValidationException::class);
        $this->expectExceptionMessage('cod_amount');

        $this->builder->build(
            'GHNS1',
            'Lạng Sơn',
            'Xã Tân Thanh',
            new GhnParcelPlan(2, 1000, 10, 10, 10, null),
            $this->address,
            1,
            'KHONGCHOXEMHANG',
            'content',
            -1
        );
    }

    public function testIncompleteRecipientFailsClosed(): void
    {
        $this->telephone = '';

        try {
            $this->builder->build(
                'GHNS1',
                'Lạng Sơn',
                'Xã Tân Thanh',
                new GhnParcelPlan(2, 1000, 10, 10, 10, null),
                $this->address,
                1,
                'KHONGCHOXEMHANG',
                'content',
                0
            );
            $this->fail('Expected GhnCreateValidationException');
        } catch (GhnCreateValidationException $exception) {
            $this->assertSame(GhnCreateValidationException::REASON_INVALID_PARCEL, $exception->getReasonToken());
        }
    }

    /**
     * TASK-4P33TV — every RequiredNote source-model option MUST be accepted by the builder.
     * Sandbox-verified set (shop 200537, 2026-09-30): KHONGCHOXEMHANG / CHOXEMHANGKHONGTHU /
     * CHOTHUHANG accepted; CHOXEMHANG + CHOTHUHANGKHONGDOI rejected by the provider and
     * REMOVED from the source model (they shipped in the dropdown and fail-closed CREATE —
     * shipment 21).
     */
    public function testEverySourceModelRequiredNoteOptionIsAccepted(): void
    {
        $options = (new \Secomm\Ghn\Model\Config\Source\RequiredNote())->toOptionArray();
        foreach ($options as $index => $option) {
            $payload = $this->builder->build(
                'GHNS-' . $index,
                'Lạng Sơn',
                'Xã Tân Thanh',
                new GhnParcelPlan(2, 1000, 10, 10, 10, null),
                $this->address,
                1,
                (string) $option['value'],
                'content',
                0
            );

            $this->assertSame($option['value'], $payload['required_note']);
        }
    }

    /** TASK-4P33TV — the provider-rejected legacy tokens stay rejected by the builder. */
    public function testProviderRejectedLegacyTokensStillFailClosed(): void
    {
        foreach (['CHOXEMHANG', 'CHOTHUHANGKHONGDOI'] as $legacyToken) {
            try {
                $this->builder->build(
                    'GHNS-X',
                    'Lạng Sơn',
                    'Xã Tân Thanh',
                    new GhnParcelPlan(2, 1000, 10, 10, 10, null),
                    $this->address,
                    1,
                    $legacyToken,
                    'content',
                    0
                );
                $this->fail("Expected GhnCreateValidationException for {$legacyToken}");
            } catch (GhnCreateValidationException $exception) {
                $this->assertSame(GhnCreateValidationException::REASON_INVALID_CONFIGURATION, $exception->getReasonToken());
            }
        }
    }
}
