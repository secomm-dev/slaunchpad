<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;

class PaymentAttemptResource extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'secomm_zalopay_payment_attempt_resource_model';

    /**
     * Initialize resource model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('secomm_zalopay_payment_attempt', PaymentAttemptInterface::ENTITY_ID);
        $this->_useIsObjectNew = true;
    }

    /**
     * Fetch the raw attempt row with SELECT ... FOR UPDATE.
     *
     * The caller must hold an open DB transaction; the row lock is released
     * on commit/rollback.
     *
     * @param string $appTransId
     * @return array|null
     */
    public function lockRowByAppTransId(string $appTransId): ?array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where(PaymentAttemptInterface::APP_TRANS_ID . ' = ?', $appTransId)
            ->forUpdate(true);

        $row = $connection->fetchRow($select);

        return $row ?: null;
    }

    /**
     * Atomically claim the order confirmation email dispatch for one attempt
     * row. The conditional UPDATE succeeds only when no claim is held
     * (email_dispatch IS NULL) or the held claim is stale enough to reclaim
     * (older than the grace period - a sender that crashed after claiming
     * but before sending); the caller's FOR UPDATE row lock (the finalizer
     * holds it inside its transaction) serializes concurrent claimers, so a
     * claim can never be double-granted while the lock is held.
     *
     * @param int $entityId
     * @param int $token Claim token (current unix ts).
     * @param int $graceSeconds Age at which an existing claim is reclaimable.
     * @return bool True if THIS call now holds the claim.
     */
    public function claimEmailDispatch(int $entityId, int $token, int $graceSeconds): bool
    {
        $cutoff = $token - $graceSeconds;
        $where = sprintf(
            '%s = %d AND (%s IS NULL OR %s <= %d)',
            PaymentAttemptInterface::ENTITY_ID,
            $entityId,
            PaymentAttemptInterface::EMAIL_DISPATCH,
            PaymentAttemptInterface::EMAIL_DISPATCH,
            $cutoff
        );
        $affected = $this->getConnection()->update(
            $this->getMainTable(),
            [PaymentAttemptInterface::EMAIL_DISPATCH => $token],
            $where
        );

        return $affected > 0;
    }

    /**
     * Release THIS caller's claim (token-guarded: a claim taken over by a
     * newer sender after the grace period is never released by a stale
     * owner). Runs after the send attempt regardless of its outcome - the
     * durable "sent" record is the order's email_sent, not the claim.
     *
     * @param int $entityId
     * @param int $token
     * @return void
     */
    public function releaseEmailDispatch(int $entityId, int $token): void
    {
        $where = sprintf(
            '%s = %d AND %s = %d',
            PaymentAttemptInterface::ENTITY_ID,
            $entityId,
            PaymentAttemptInterface::EMAIL_DISPATCH,
            $token
        );
        $this->getConnection()->update(
            $this->getMainTable(),
            [PaymentAttemptInterface::EMAIL_DISPATCH => null],
            $where
        );
    }
}
