<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Model\Lifecycle;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime as DateLib;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Secomm\PaymentCore\Api\Data\PaymentInterface;
use Secomm\PaymentCore\Api\PaymentRepositoryInterface;

/**
 * FEAT-CSWYEJ / DEC-FEATCSWYEJ-004 — cancel ONE expired pending order through
 * the Magento lifecycle.
 *
 * Simplified semantics (user TL 2026-08-25): expired + still pending = NOT PAID.
 * The provider pre-cancel verification (querydr) was REMOVED from the cron —
 * the order state IS the payment truth: if VNPAY had confirmed, the IPN would
 * have moved the order out of pending before the expiry window closed.
 *
 * Layer 1: per-order DB lock (LockManager, non-blocking)
 * Layer 2: order state reload + canCancel() right before cancel
 */
class CancelExpiredOrder
{
    private const LOCK_PREFIX = 'paymentcore_cancel_';

    public function __construct(
        private readonly OrderManagementInterface $orderManagement,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PaymentRepositoryInterface $repository,
        private readonly LockManagerInterface $lockManager,
        private readonly DateLib $dateLib,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Process one expired record. Never throws — every failure keeps the record
     * active for the next run (AC-012).
     */
    public function process(PaymentInterface $record): void
    {
        $incrementId = $this->orderIncrementId($record);
        $lockName = self::LOCK_PREFIX . $record->getOrderId();
        if (!$this->lockManager->lock($lockName, 0)) {
            $this->logger->info(sprintf(
                '[cancel_skipped] order %s — lock busy (concurrent process handling it)',
                $incrementId
            ));
            return;
        }
        try {
            $this->cancelIfStillPending($record, $incrementId);
        } catch (\Exception $exception) {
            $this->logger->error(sprintf(
                '[cancel_failed] order %s — %s',
                $incrementId,
                $exception->getMessage()
            ));
        } finally {
            $this->lockManager->unlock($lockName);
        }
    }

    /**
     * Expired + still pending = not paid → cancel via the standard lifecycle.
     * Runs under the lock, on a freshly loaded order row.
     */
    private function cancelIfStillPending(PaymentInterface $record, string $incrementId): void
    {
        $order = $this->loadOrder($record);
        if ($order === null) {
            $this->resolveRecord($record, PaymentInterface::STATUS_ERROR, 'order deleted');
            return;
        }

        $state = (string)$order->getState();
        if ($state === Order::STATE_PROCESSING
            || $state === Order::STATE_COMPLETE
            || $state === Order::STATE_CLOSED
        ) {
            // Payment landed (IPN/webhook moved the state) — record as completed.
            $this->resolveRecord($record, PaymentInterface::STATUS_COMPLETED, "state={$state}");
            return;
        }
        if ($state === Order::STATE_CANCELED) {
            $this->resolveRecord($record, PaymentInterface::STATUS_CANCELED, 'already canceled');
            return;
        }
        if (!$order->canCancel()) {
            // Invoice created / any other lifecycle block — next run re-checks.
            $this->logger->info(sprintf(
                '[cancel_skipped] order %s — canCancel() false (state=%s)',
                $incrementId,
                $state
            ));
            return;
        }

        $this->orderManagement->cancel($record->getOrderId());
        $this->resolveRecord($record, PaymentInterface::STATUS_CANCELED, 'expired still pending');
        $this->logger->info(sprintf(
            '[cancel_success] order %s — expired still pending, canceled via lifecycle',
            $incrementId
        ));
    }

    /**
     * Freshly load the order; null when the row is gone.
     */
    private function loadOrder(PaymentInterface $record): ?OrderInterface
    {
        try {
            return $this->orderRepository->get($record->getOrderId());
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    /**
     * Flip the record out of active and persist.
     */
    private function resolveRecord(PaymentInterface $record, string $status, string $reason): void
    {
        $record->setStatus($status)
            ->setResolvedAt($this->dateLib->gmtDate('Y-m-d H:i:s'));
        $this->repository->save($record);
        $this->logger->info(sprintf(
            '[record_resolved] order %s — status=%s (%s)',
            $this->orderIncrementId($record),
            $status,
            $reason
        ));
    }

    /**
     * Best-effort increment id for log lines.
     */
    private function orderIncrementId(PaymentInterface $record): string
    {
        try {
            return (string)$this->orderRepository->get($record->getOrderId())->getIncrementId();
        } catch (\Exception) {
            return (string)$record->getOrderId();
        }
    }
}
