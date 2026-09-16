<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Setup\Patch\Data;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Setup\Patch\Data\BackfillRefundState;

/**
 * TASK-CG6BM7 corrective round 3 (HIGH F14): the refund_state backfill
 * data patch classification matrix.
 *
 *  - historical RESOLVED rows (is_processed=1) MUST NOT become active
 *    blocking refunds: explicit refusal evidence -> CONFIRMED_FAIL, every
 *    other resolved row -> CONFIRMED_SUCCESS (never blocks);
 *  - unresolved rows (is_processed=0) land conservative UNKNOWN (keeps
 *    blocking - safe side).
 */
class BackfillRefundStateTest extends TestCase
{
    /**
     * GATE: the patch classifies the three historical cohorts in evidence
     * order - refusal evidence first, then all remaining resolved rows,
     * then unresolved - each with the exact WHERE clause.
     */
    public function testApplyBackfillsThreeCohortsInEvidenceOrder(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->with('zalo_pay_refund')->willReturn('zalo_pay_refund');

        $calls = [];
        $connection->expects($this->exactly(3))->method('update')
            ->willReturnCallback(function (string $table, array $bind, array $where) use (&$calls): int {
                $calls[] = [$table, $bind, $where];

                return 1;
            });

        (new BackfillRefundState($setup))->apply();

        $this->assertSame('zalo_pay_refund', $calls[0][0]);
        $this->assertSame(
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_FAIL],
            $calls[0][1]
        );
        $this->assertSame(
            [
                RefundInterface::IS_PROCESSED . ' = ?' => 1,
                RefundInterface::LAST_ERROR . ' LIKE ?' => RefundInterface::STATE_EVIDENCE_REFUND_FAILED . '%',
            ],
            $calls[0][2]
        );

        $this->assertSame(
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS],
            $calls[1][1]
        );
        $this->assertSame(
            [RefundInterface::IS_PROCESSED . ' = ?' => 1],
            $calls[1][2]
        );

        $this->assertSame(
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_UNKNOWN],
            $calls[2][1]
        );
        $this->assertSame(
            [RefundInterface::IS_PROCESSED . ' = ?' => 0],
            $calls[2][2]
        );
    }

    /**
     * GATE: the patch runs once (no aliases, no dependencies - it is the
     * last data patch of this release).
     */
    public function testPatchHasNoDependenciesOrAliases(): void
    {
        $this->assertSame([], BackfillRefundState::getDependencies());
        $this->assertSame([], (new BackfillRefundState(
            $this->createMock(ModuleDataSetupInterface::class)
        ))->getAliases());
    }
}
