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
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Setup\Patch\Data\BackfillRefundState;

/**
 * TASK-CG6BM7 corrective round 4 (F19/F20): the refund_state backfill +
 * claim-ownership data patch.
 *
 *  - cohort 1: is_processed=1 + refund_failed evidence -> CONFIRMED_FAIL;
 *  - cohort 2 (is_processed=1 + last_error IS NULL) is disjoint from
 *    cohort 1 - the fail classification is NEVER overwritten (F20);
 *  - cohort 3: is_processed=1 + OTHER error evidence -> UNKNOWN quarantine
 *    (ambiguous history is never inferred success, F20);
 *  - cohort 4: is_processed=0 -> UNKNOWN (conservative blocking);
 *  - claim ownership (F19): the lowest entity_id UNKNOWN row per order
 *    claims active_claim=1 (the unique (order_id, active_claim) index then
 *    blocks new claims exactly like a live claim); the rest stay NULL as
 *    reconciliation evidence.
 */
class BackfillRefundStateTest extends TestCase
{
    /**
     * GATE (F20): the patch classifies the four historical cohorts in
     * evidence order with the exact WHERE clauses, and the claim-ownership
     * UPDATE deterministically pins ONE unresolved row per order (F19).
     */
    public function testApplyBackfillsFourCohortsInEvidenceOrder(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->with('zalo_pay_refund')->willReturn('zalo_pay_refund');

        $updates = [];
        $queries = [];
        $connection->expects($this->exactly(4))->method('update')
            ->willReturnCallback(function (string $table, array $bind, array|string $where) use (&$updates): int {
                $updates[] = [$table, $bind, $where];

                return 1;
            });
        $connection->expects($this->once())->method('query')
            ->willReturnCallback(function (string $sql) use (&$queries): void {
                $queries[] = $sql;
            });
        $connection->method('quoteInto')->willReturnCallback(
            function (string $text, mixed $value): string {
                $values = is_array($value) ? array_map('strval', array_values($value)) : [(string)$value];
                foreach ($values as $v) {
                    $text = preg_replace('/\?/', $v, $text, 1);
                }

                return $text;
            }
        );
        $connection->method('quoteIdentifier')->willReturnCallback(
            fn (string $ident): string => '`' . $ident . '`'
        );

        (new BackfillRefundState($setup))->apply();

        // Cohort 1: explicit refusal evidence -> CONFIRMED_FAIL.
        $this->assertSame('zalo_pay_refund', $updates[0][0]);
        $this->assertSame(
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_FAIL],
            $updates[0][1]
        );
        $this->assertSame(
            [
                RefundInterface::IS_PROCESSED . ' = ?' => 1,
                RefundInterface::LAST_ERROR . ' LIKE ?' => RefundInterface::STATE_EVIDENCE_REFUND_FAILED . '%',
            ],
            $updates[0][2]
        );

        // Cohort 2: resolved with NO error evidence -> CONFIRMED_SUCCESS,
        // and the last_error IS NULL condition keeps it disjoint from
        // cohort 1 (the F20 overwrite is impossible by construction).
        $this->assertSame(
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS],
            $updates[1][1]
        );
        $this->assertSame(
            [
                RefundInterface::IS_PROCESSED . ' = ?' => 1,
                RefundInterface::LAST_ERROR . ' IS NULL',
            ],
            $updates[1][2]
        );

        // Cohort 3: resolved with OTHER error evidence -> UNKNOWN quarantine
        // (string WHERE from quoteInto - ambiguity NEVER becomes success).
        $this->assertSame(
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_UNKNOWN],
            $updates[2][1]
        );
        $this->assertIsString($updates[2][2]);
        $this->assertStringContainsString(RefundInterface::IS_PROCESSED . ' = 1', $updates[2][2]);
        $this->assertStringContainsString(RefundInterface::LAST_ERROR . ' IS NOT NULL', $updates[2][2]);
        $this->assertStringContainsString(RefundInterface::LAST_ERROR . ' NOT LIKE', $updates[2][2]);

        // Cohort 4: unresolved -> UNKNOWN (conservative blocking).
        $this->assertSame(
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_UNKNOWN],
            $updates[3][1]
        );
        $this->assertSame(
            [RefundInterface::IS_PROCESSED . ' = ?' => 0],
            $updates[3][2]
        );

        // Claim ownership (F19): MIN(entity_id) per order among UNKNOWN rows.
        $this->assertCount(1, $queries);
        $sql = $queries[0];
        $this->assertStringContainsString('UPDATE `zalo_pay_refund` AS r', $sql);
        $this->assertStringContainsString('MIN(`entity_id`) AS claim_id', $sql);
        $this->assertStringContainsString("WHERE `refund_state` = 'unknown'", $sql);
        $this->assertStringContainsString('GROUP BY `order_id`', $sql);
        $this->assertStringContainsString('ON r.`entity_id` = c.claim_id', $sql);
        $this->assertStringContainsString('SET r.`active_claim` = 1', $sql);
    }

    /**
     * GATE: the patch runs once (no aliases, no dependencies).
     */
    public function testPatchHasNoDependenciesOrAliases(): void
    {
        $this->assertSame([], BackfillRefundState::getDependencies());
        $this->assertSame([], (new BackfillRefundState(
            $this->createMock(ModuleDataSetupInterface::class)
        ))->getAliases());
    }
}
