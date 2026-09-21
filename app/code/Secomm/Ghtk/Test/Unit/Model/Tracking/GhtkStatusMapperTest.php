<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\Tracking;

use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\Tracking\GhtkStatusMapper;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;

/**
 * TASK-KCXKVR — the mapping table must mirror the OFFICIAL GHTK status table
 * (api.ghtk.vn webhook docs; evidence SPIKE-A1DGPY §6). The legacy unofficial
 * meanings (6=gặp lỗi giao, 12=chuyển hoàn, …) are GONE.
 */
class GhtkStatusMapperTest extends TestCase
{
    private GhtkStatusMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new GhtkStatusMapper();
    }

    /**
     * @dataProvider ghtkCodes
     */
    public function testKnownCodes(mixed $code, string $expected): void
    {
        $this->assertSame($expected, $this->mapper->map($code));
    }

    public function ghtkCodes(): array
    {
        return [
            'cancel -1' => [-1, NormalizedTrackingStatus::CANCELLED],
            'pending 1' => ['1', NormalizedTrackingStatus::CREATED],
            'accepted 2' => ['2', NormalizedTrackingStatus::PICKING],
            'picked 3' => ['3', NormalizedTrackingStatus::PICKED_UP],
            // Official 4 = "Đã điều phối giao hàng/Đang giao hàng" → out for delivery.
            'out for delivery 4' => ['4', NormalizedTrackingStatus::OUT_FOR_DELIVERY],
            'delivered 5' => ['5', NormalizedTrackingStatus::DELIVERED],
            // Official 6 = "Đã đối soát" — financial post-delivery, stays DELIVERED.
            'reconciled 6 stays delivered' => ['6', NormalizedTrackingStatus::DELIVERED],
            // Official 7 = "Không lấy được hàng" — pickup failure, non-terminal failed-state.
            'pickup failed 7' => ['7', NormalizedTrackingStatus::DELIVERY_FAILED],
            // Official 8 = "Hoãn lấy hàng" — delay inside the pickup loop.
            'pickup delayed 8' => ['8', NormalizedTrackingStatus::PICKING],
            // Official 9 = "Không giao được hàng" — delivery failed (reattempt possible).
            'delivery failed 9' => ['9', NormalizedTrackingStatus::DELIVERY_FAILED],
            'delivery delayed 10' => ['10', NormalizedTrackingStatus::OUT_FOR_DELIVERY],
            // Official 11 = "Đã đối soát công nợ trả hàng" — terminal return reconciliation.
            'return reconciled 11' => ['11', NormalizedTrackingStatus::RETURNED],
            // Official 12 = "Đã điều phối lấy hàng/Đang lấy hàng" — PICKUP flow start, NOT returning.
            'picking 12 not returning' => ['12', NormalizedTrackingStatus::PICKING],
            // Official 13 = "Đơn hàng bồi hoàn" (compensation) — best existing terminal semantic.
            'compensation 13' => ['13', NormalizedTrackingStatus::RETURNED],
            'returning 20' => ['20', NormalizedTrackingStatus::RETURNING],
            // Official 21 = "Đã trả hàng" — previously MISSING (→ UNKNOWN).
            'returned 21' => ['21', NormalizedTrackingStatus::RETURNED],
            // int input equivalence
            'int 12' => [12, NormalizedTrackingStatus::PICKING],
            'int 21' => [21, NormalizedTrackingStatus::RETURNED],
        ];
    }

    public function testUnknownCodeMapsToUnknown(): void
    {
        $this->assertSame(NormalizedTrackingStatus::UNKNOWN, $this->mapper->map('9999'));
        $this->assertSame(NormalizedTrackingStatus::UNKNOWN, $this->mapper->map(''));
        $this->assertSame(NormalizedTrackingStatus::UNKNOWN, $this->mapper->map('Đây là trạng thái mới'));
    }

    /**
     * Shipper-reported statuses are INFORMATIONAL ONLY per the official docs
     * ("không phải trạng thái của đơn hàng") — never lifecycle states.
     */
    public function testInformationalShipperCodesMapToUnknown(): void
    {
        foreach (['123', '127', '128', '45', '49', '410'] as $code) {
            $this->assertSame(
                NormalizedTrackingStatus::UNKNOWN,
                $this->mapper->map($code),
                'Shipper informational code ' . $code . ' must not become a lifecycle status'
            );
        }
    }

    /**
     * Terminal preservation (§6): a reconciled update (6) after a delivered
     * update (5) maps to the SAME terminal status — the pipeline can never be
     * downgraded out of DELIVERED by financial post-states.
     */
    public function testDeliveredThenReconciledPreservesDeliveredTerminal(): void
    {
        $this->assertSame(
            $this->mapper->map('5'),
            $this->mapper->map('6'),
            'Reconciled (6) must preserve the DELIVERED terminal semantic of Delivered (5)'
        );
        $this->assertTrue(NormalizedTrackingStatus::isTerminal($this->mapper->map('6')));
    }

    public function testReturnReconciliationPreservesReturnedTerminal(): void
    {
        foreach (['11', '13', '21'] as $code) {
            $this->assertTrue(
                NormalizedTrackingStatus::isTerminal($this->mapper->map($code)),
                'Return-terminal code ' . $code . ' must map to a terminal status'
            );
        }
    }
}
