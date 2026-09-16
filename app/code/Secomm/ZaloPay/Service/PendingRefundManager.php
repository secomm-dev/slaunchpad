<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\RefundOperation;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Gateway\Command\RefundOutcome;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Model\RefundModel;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollectionFactory;
use Secomm\ZaloPay\Model\ResourceModel\RefundResource;
use Secomm\ZaloPay\Plugin\Model\Order\CreditmemoPlugin;

/**
 * Durable pending-refund lifecycle for async ZaloPay refunds
 * (TASK-CG6BG7 corrective round).
 *
 * Invariant: ZaloPay PROCESSING != Magento refund completed. While a refund
 * is pending at the provider, Magento does NOT mutate total_refunded /
 * qty_refunded and the order does NOT become CLOSED; the local finalization
 * runs exactly once, only on confirmed provider SUCCESS, through the NATIVE
 * core accounting (RefundOperation + Payment::refund with the gateway
 * provider call skipped via RefundOutcomeMarker).
 */
class PendingRefundManager
{
    /**
     * Bounded v2/query_refund budget shared with RefundCronjob: 96 runs on
     * the 15-minute schedule (~24h). Terminal outcomes saturate the budget
     * so the row drops out of the cron selection (never loops silently).
     */
    public const MAX_QUERY_ATTEMPTS = 96;

    /**
     * Prefix for reconciliation evidence in last_error (safe text only).
     */
    public const EVIDENCE_RECONCILE = 'reconcile_error: ';

    /**
     * Prefix for transport evidence in last_error (safe text only).
     */
    public const EVIDENCE_TRANSPORT = 'transport_error: ';

    /**
     * Prefix for terminal provider-refusal evidence in last_error (safe,
     * provider-map text only).
     */
    public const EVIDENCE_REFUND_FAILED = 'refund_failed: ';

    /**
     * Prefix for provider protocol anomaly evidence in last_error.
     */
    public const EVIDENCE_ANOMALY = 'protocol_anomaly: ';

    /**
     * @param RefundResource $refundResource Refund resource: connection, transaction, row lock.
     * @param RefundCollectionFactory $refundCollectionFactory
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param RefundOperation $refundOperation Native core refund accounting.
     * @param RefundOutcomeMarker $outcomeMarker Makes the gateway skip the provider call.
     * @param Logger $logger
     */
    public function __construct(
        private readonly RefundResource                $refundResource,
        private readonly RefundCollectionFactory       $refundCollectionFactory,
        private readonly CreditmemoRepositoryInterface $creditmemoRepository,
        private readonly InvoiceRepositoryInterface    $invoiceRepository,
        private readonly OrderRepositoryInterface      $orderRepository,
        private readonly RefundOperation               $refundOperation,
        private readonly RefundOutcomeMarker           $outcomeMarker,
        private readonly Logger                        $logger
    ) {
    }

    /**
     * Whether an un-resolved provider refund is being tracked for the order:
     * a NOT_PROCESSED row within the bounded query budget. While in flight,
     * a NEW refund request for the same order is refused: the local
     * refundable balance does not yet reflect the pending provider refund,
     * so allowing a second request could refund the same money twice.
     *
     * @param int $orderId
     * @return bool
     */
    public function hasInFlight(int $orderId): bool
    {
        $collection = $this->refundCollectionFactory->create();
        $collection->addFieldToFilter(RefundInterface::ORDER_ID, ['eq' => $orderId]);
        $collection->addFieldToFilter(RefundInterface::IS_PROCESSED, ['eq' => RefundInterface::NOT_PROCESSED]);
        $collection->addFieldToFilter(RefundInterface::QUERY_ATTEMPTS, ['lt' => self::MAX_QUERY_ATTEMPTS]);

        return (int)$collection->getSize() > 0;
    }

    /**
     * Persist the durable pending-refund track for a refund the provider has
     * ACCEPTED (PROCESSING) or whose outcome is unknown (transport failure):
     * creditmemo -> PROCESSING state (CreditmemoPlugin::STATE_PROCESSING) and
     * one zalo_pay_refund row carrying the v2/query_refund payload the cron
     * replays (re-signed) to reconcile - never to re-request - the refund.
     *
     * @param CreditmemoInterface|Creditmemo $creditmemo
     * @param RefundOutcome $outcome Provider outcome for this refund.
     * @param string|null $lastError Initial evidence (transport case), null on PROCESSING.
     * @return RefundModel The persisted refund row.
     * @throws LocalizedException If the durable track cannot be persisted:
     *         the refund may already be accepted at the provider - it is
     *         critical-logged for manual reconciliation, never swallowed.
     */
    public function registerPending(
        CreditmemoInterface $creditmemo,
        RefundOutcome $outcome,
        ?string $lastError = null
    ): RefundModel {
        $connection = $this->refundResource->getConnection();
        $connection->beginTransaction();
        try {
            $creditmemo->setState(CreditmemoPlugin::STATE_PROCESSING);
            $this->creditmemoRepository->save($creditmemo);

            $refund = $this->refundCollectionFactory->create()->getNewEmptyItem();
            $refund->setData(
                [
                    RefundInterface::ORDER_ID => (int)$creditmemo->getOrderId(),
                    RefundInterface::CREDIT_MEMO_ID => (int)$creditmemo->getEntityId(),
                    RefundInterface::INCREMENT_ID => (string)$creditmemo->getOrder()->getIncrementId(),
                    RefundInterface::M_REFUND_ID => $outcome->getMRefundId(),
                    RefundInterface::ADDITIONAL_INFORMATION => (string)$outcome->getQueryPayload(),
                    RefundInterface::AMOUNT => (float)$outcome->getVndAmount(),
                    RefundInterface::IS_PROCESSED => RefundInterface::NOT_PROCESSED,
                    RefundInterface::QUERY_ATTEMPTS => 0,
                    RefundInterface::LAST_ERROR => $lastError,
                ]
            );
            $refund->save();

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            $this->logger->critical(
                sprintf(
                    'ZaloPay refund %s (order %s): the refund is accepted by the provider but the local pending track could not be persisted: %s - manual reconciliation required.',
                    $outcome->getMRefundId(),
                    (string)$creditmemo->getOrder()->getIncrementId(),
                    $exception->getMessage()
                )
            );

            throw new LocalizedException(
                __('Zalopay: Refund is in progress at the provider but could not be tracked locally. Please contact support with the order number.')
            );
        }

        return $refund;
    }

    /**
     * Consume one unit of the bounded query budget with observable state
     * progression: increments query_attempts and records safe evidence.
     * Used by RefundCronjob so EVERY genuine attempt (query transport
     * failure, protocol anomaly, finalize failure) is measurable - rows
     * never sit selected-forever (BLOCKER 2 fix).
     *
     * @param RefundModel $refund
     * @param string|null $evidence Safe evidence text (null clears a stale error).
     * @return int The new attempts value.
     */
    public function consumeQueryBudget(RefundModel $refund, ?string $evidence): int
    {
        $attempts = (int)$refund->getData(RefundInterface::QUERY_ATTEMPTS) + 1;
        $refund->setData(RefundInterface::QUERY_ATTEMPTS, $attempts);
        $refund->setData(RefundInterface::LAST_ERROR, $evidence);
        $refund->save();

        return $attempts;
    }

    /**
     * Terminate a row without finalizing: saturate the query budget (the row
     * drops out of the cron selection, evidence retained - RefundCleanup
     * only deletes PROCESSED rows) and record safe evidence. Used for
     * non-retryable classifications: provider FAIL, malformed stored payload,
     * missing creditmemo, creditmemo state drift.
     *
     * @param RefundModel $refund
     * @param string $evidence Safe evidence text.
     * @return void
     */
    public function terminate(RefundModel $refund, string $evidence): void
    {
        $refund->setData(RefundInterface::QUERY_ATTEMPTS, self::MAX_QUERY_ATTEMPTS);
        $refund->setData(RefundInterface::LAST_ERROR, $evidence);
        $refund->save();
    }

    /**
     * Finalize a provider-confirmed refund exactly once through the NATIVE
     * core accounting - the gateway provider call is skipped via
     * RefundOutcomeMarker, the accounting replicates CreditmemoService's
     * success path minus the provider interaction: invoice update ->
     * RefundOperation (order refund totals + payment transactions) ->
     * creditmemo save -> order save, all in one transaction guarded by
     * SELECT ... FOR UPDATE on the refund row (a concurrent or repeated
     * run re-checks is_processed under the lock and never re-finalizes).
     *
     * @param RefundModel $refund The pending refund row.
     * @return bool True if THIS call finalized it; false if already finalized.
     * @throws LocalizedException On local accounting failure: the transaction
     *         is rolled back (provider money is out but local accounting is
     *         not - critical-logged; the row keeps pending evidence and its
     *         remaining query budget for the next cron attempt).
     */
    public function finalizeSuccess(RefundModel $refund): bool
    {
        $connection = $this->refundResource->getConnection();
        $connection->beginTransaction();
        try {
            $locked = $this->lockRefundRow((int)$refund->getId());
            if ($locked === null || (bool)$locked[RefundInterface::IS_PROCESSED]) {
                // Lost the race: another run already finalized this refund.
                $connection->commit();

                return false;
            }

            $creditmemo = $this->creditmemoRepository->get((int)$locked[RefundInterface::CREDIT_MEMO_ID]);
            if ((int)$creditmemo->getState() === Creditmemo::STATE_REFUNDED) {
                // Recovery: the local accounting already happened (e.g. a
                // crash between the creditmemo save and the row update) -
                // complete only the bookkeeping, never re-run accounting.
                $connection->update(
                    $this->refundResource->getMainTable(),
                    [RefundInterface::IS_PROCESSED => 1, RefundInterface::LAST_ERROR => null],
                    [RefundInterface::ENTITY_ID . ' = ?' => (int)$refund->getId()]
                );
                $connection->commit();

                return false;
            }

            $order = $creditmemo->getOrder();

            $invoice = null;
            if ((int)$creditmemo->getInvoiceId() > 0) {
                $invoice = $this->invoiceRepository->get((int)$creditmemo->getInvoiceId());
                $creditmemo->setInvoice($invoice);
            }

            // Core success-path replication (CreditmemoService::refund lines
            // 152-177, MINUS the gateway provider interaction which is skipped
            // via the marker below).
            if ($invoice !== null) {
                $invoice->setIsUsedForRefund(true);
                $invoice->setBaseTotalRefunded(
                    (float)$invoice->getBaseTotalRefunded() + (float)$creditmemo->getBaseGrandTotal()
                );
                $this->invoiceRepository->save($invoice);
            }

            $creditmemo->setState(Creditmemo::STATE_REFUNDED);
            // One provider interaction per refund: the outcome is already
            // known (SUCCESS) - the gateway refund command must NOT re-ask
            // the provider while Payment::refund runs the gateway command.
            $this->outcomeMarker->markProviderAlreadyAsked((int)$order->getId());
            $this->refundOperation->execute($creditmemo, $order, true);

            $this->creditmemoRepository->save($creditmemo);
            $this->orderRepository->save($order);

            $connection->update(
                $this->refundResource->getMainTable(),
                [RefundInterface::IS_PROCESSED => 1, RefundInterface::LAST_ERROR => null],
                [RefundInterface::ENTITY_ID . ' = ?' => (int)$refund->getId()]
            );
            $connection->commit();
        } catch (NoSuchEntityException $exception) {
            $connection->rollBack();
            throw $exception;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw new LocalizedException(
                __('Zalopay: refund finalization failed locally: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }

    /**
     * SELECT ... FOR UPDATE on one refund row (the exact-once finalize
     * guard; same connection-scoped pattern as
     * PaymentAttemptResource::lockRowByAppTransId).
     *
     * @param int $refundId
     * @return array|null The locked row, or null when it no longer exists.
     */
    private function lockRefundRow(int $refundId): ?array
    {
        $connection = $this->refundResource->getConnection();
        $select = $connection->select()
            ->from($this->refundResource->getMainTable())
            ->where(RefundInterface::ENTITY_ID . ' = ?', $refundId)
            ->forUpdate(true);

        $row = $connection->fetchRow($select);

        return $row ?: null;
    }
}
