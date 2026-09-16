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
 * TASK-CG6BM7 corrective round 3 (HIGH F14): backfill the semantic
 * refund_state for rows that predate the column.
 *
 * The declarative column default ("processing") would turn HISTORICAL
 * rows into active-looking blocking refunds after upgrade:
 *  - is_processed = 1 rows are RESOLVED history - they must never block;
 *  - is_processed = 0 rows are genuinely unresolved - conservative
 *    UNKNOWN keeps them blocking (safe side).
 *
 * Safe migration principles (TL round 3):
 *  - historical is_processed=1          -> MUST NOT become an active block
 *  - historical unresolved (processed=0) -> conservative UNKNOWN is OK
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
     * Evidence-based one-shot backfill (runs once via the patch list;
     * rows created AFTER this release always carry an explicit state).
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

        // 2. Every remaining RESOLVED row (is_processed=1, incl. legacy
        //    terminal outcomes without the round-1 evidence prefix) lands
        //    CONFIRMED_SUCCESS: a resolved row must NEVER block a future
        //    refund; its accounting was already applied by the flow that
        //    resolved it.
        $connection->update(
            $table,
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_CONFIRMED_SUCCESS],
            [RefundInterface::IS_PROCESSED . ' = ?' => 1]
        );

        // 3. Unresolved rows keep CONSERVATIVE blocking semantics: UNKNOWN
        //    (outcome never confirmed by this lifecycle) - they stay
        //    visible and blocking until deliberately resolved.
        $connection->update(
            $table,
            [RefundInterface::REFUND_STATE => RefundInterface::REFUND_STATE_UNKNOWN],
            [RefundInterface::IS_PROCESSED . ' = ?' => 0]
        );

        return $this;
    }
}
