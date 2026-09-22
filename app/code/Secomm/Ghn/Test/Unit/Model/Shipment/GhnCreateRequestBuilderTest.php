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
 * TASK-9Q5ZAK r2 (DEC-TASK9Q5ZAK-001) — the Create Order payload contract: the interpreter's
 * plan decides root fields vs items[]; config enums validated fail-closed; and every
 * architecture-mandated ABSENCE asserted (no COD, no insurance/order value, no sender/return,
 * no service_id).
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
            'Waffle Blanket x1'
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
            'ignored when items present'
        );

        $this->assertSame(5, $payload['service_type_id']);
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
            'ignored'
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
            'content'
        );

        foreach ([
            'cod_amount', 'cod_failed_amount', 'insurance_value', 'order_value',
            'from_name', 'from_phone', 'from_address', 'from_ward_name',
            'from_district_name', 'from_province_name', 'return_name', 'return_phone',
            'return_address', 'return_district_name', 'service_id', 'coupon',
        ] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $payload, "$forbidden must never be sent by GHN-D");
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
            'content'
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
                'content'
            );
            $this->fail('Expected GhnCreateValidationException');
        } catch (GhnCreateValidationException $exception) {
            $this->assertSame(GhnCreateValidationException::REASON_INVALID_CONFIGURATION, $exception->getReasonToken());
        }
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
                'content'
            );
            $this->fail('Expected GhnCreateValidationException');
        } catch (GhnCreateValidationException $exception) {
            $this->assertSame(GhnCreateValidationException::REASON_INVALID_PARCEL, $exception->getReasonToken());
        }
    }
}
