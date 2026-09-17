<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Secomm\ZaloPay\Api\Data\RefundInterface;

/**
 * TASK-CG6BM7 corrective round 3/4 (F14/F19/F20): backfill the semantic
 * refund_state AND the atomic claim ownership for rows that predate the
 * columns.
 *
 * Evidence-ordered cohorts (round 4 F20 - a cohort can never be overwritten
 * by a later one; ambiguous evidence NEVER becomes success):
 *
 *  1. is_processed = 1 AND last_error LIKE 'refund_failed:%'  -> confirmed_fail
 *  2. is_processed = 1 AND last_error IS NULL                 -> confirmed_success
 *     (legacy is_processed=1 was ONLY written by the success path)
 *  3. is_processed = 1 AND last_error set WITHOUT the fail prefix -> UNKNOWN
 *     (ambiguous historical evidence - conservative quarantine, F20)
 *  4. is_processed = 0                                        -> UNKNOWN
 *
 * Claim ownership (round 4 F19): the request path guards concurrency with
 * the unique (order_id, active_claim) index - a historical unresolved row
 * with active_claim=NULL would NOT conflict with a new claim, so ONE
 * deterministic unresolved row per order (lowest entity_id) claims
 * active_claim = 1 and keeps blocking new claims; the remaining unresolved
 * rows stay as reconciliation evidence (never discarded).
 */
class BackfillRefundState implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * Evidence-based one-shot backfill (runs once via the patch list; rows
     * created AFTER this release always carry an explicit state).
     *
     * @return $this
     */
    public function apply(): BackfillRefundState
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('zalo_pay_refund');

        // 1. Resolved rows with explicit provider-refusal evidence land
        //    CONFIRMED_FAIL (truthful: the provider refused the money).
        $connection->update(
            $table,
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_FAIL],
            [
                RefundInterface::IS_PROCESSED . ' = ?' => 1,
                RefundInterface::LAST_ERROR . ' LIKE ?' => RefundInterface::STATE_EVIDENCE_REFUND_FAILED . '%',
            ]
        );

        // 2. Resolved rows with NO error evidence land CONFIRMED_SUCCESS
        //    (legacy success path wrote is_processed=1 and cleared
        //    last_error; a resolved row must NEVER block a future refund).
        //    The last_error IS NULL condition makes this cohort disjoint
        //    from cohort 1 - the fail classification can NEVER be
        //    overwritten by this update (round 4 F20).
        $connection->update(
            $table,
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS],
            [
                RefundInterface::IS_PROCESSED . ' = ?' => 1,
                RefundInterface::LAST_ERROR . ' IS NULL',
            ]
        );

        // 3. Resolved rows carrying OTHER error evidence (transport /
        //    reconcile prefixes) are AMBIGUOUS history - conservative
        //    UNKNOWN quarantine, never inferred success (round 4 F20).
        //    F24 (round 5): Zend quoteInto does NOT bind array elements
        //    sequentially (it str_replace's ONE quoted value into EVERY
        //    placeholder) - each placeholder gets its OWN one-value
        //    quoteInto call.
        $connection->update(
            $table,
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_UNKNOWN],
            $connection->quoteInto(
                RefundInterface::IS_PROCESSED . ' = ?', 1
            )
            . ' AND ' . RefundInterface::LAST_ERROR . ' IS NOT NULL'
            . ' AND ' . RefundInterface::LAST_ERROR . ' NOT LIKE '
            . $connection->quoteInto(
                '?',
                RefundInterface::STATE_EVIDENCE_REFUND_FAILED . '%'
            )
        );

        // 4. Unresolved rows keep CONSERVATIVE blocking semantics: UNKNOWN
        //    (outcome never confirmed by this lifecycle) - they stay
        //    visible and blocking until deliberately resolved.
        $connection->update(
            $table,
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_UNKNOWN],
            [RefundInterface::IS_PROCESSED . ' = ?' => 0]
        );

        // 5. CLAIM OWNERSHIP (round 4 F19): one deterministic unresolved row
        //    per order (lowest entity_id) OWNS the atomic claim slot - the
        //    unique (order_id, active_claim) index then blocks a new active
        //    claim for that order exactly like a live claim would. The
        //    remaining unresolved rows keep active_claim NULL as pure
        //    reconciliation evidence. Deterministic on re-run (MIN id).
        $connection->query(sprintf(
            'UPDATE %1$s AS r'
            . ' JOIN (SELECT MIN(%2$s) AS claim_id FROM %1$s WHERE %3$s = \'%4$s\' GROUP BY %5$s) AS c'
            . ' ON r.%2$s = c.claim_id'
            . ' SET r.%6$s = 1',
            $connection->quoteIdentifier($table),
            $connection->quoteIdentifier(RefundInterface::ENTITY_ID),
            $connection->quoteIdentifier(RefundInterface::REFUND_STATE),
            RefundInterface::REFUND_STATE_UNKNOWN,
            $connection->quoteIdentifier(RefundInterface::ORDER_ID),
            $connection->quoteIdentifier(RefundInterface::ACTIVE_CLAIM)
        ));

        return $this;
    }
}
