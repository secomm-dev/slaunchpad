<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Plugin\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Service\CreditmemoService;
use Secomm\ZaloPay\Exception\RefundTransportException;
use Secomm\ZaloPay\Gateway\Command\RefundCommand;
use Secomm\ZaloPay\Gateway\Command\RefundOutcome;
use Secomm\ZaloPay\Service\CreditmemoRefundPreflight;
use Secomm\ZaloPay\Service\PendingRefundManager;
use Secomm\ZaloPay\Service\RefundOutcomeMarker;

/**
 * Orchestrates the ZaloPay async refund lifecycle around the Magento core
 * refund flow (CreditmemoService::refund, the CreditmemoManagement entry
 * the admin "Refund" action calls) - TASK-CG6BM7 corrective round.
 *
 * Lifecycle per refund request (exactly ONE provider interaction):
 *
 *  1. GUARD      - offline refunds are refused; a refund still in flight
 *                  for the order blocks a second request (the local
 *                  refundable balance does not yet reflect the pending
 *                  provider refund, so a second request could refund the
 *                  same money twice);
 *  2. PROVIDER   - RefundCommand is called directly (the payment's creditmemo
 *                  + parent transaction are pinned first, replicating what
 *                  Payment::refund would do internally, because the provider
 *                  must be asked BEFORE core mutates any totals);
 *  3a. SUCCESS   - the marker pins the known outcome and the NATIVE core
 *                  flow runs (proceed()): core owns the accounting exactly
 *                  as for a synchronous gateway refund (gateway call skipped
 *                  via the marker);
 *  3b. PROCESSING- the durable pending refund is registered (creditmemo ->
 *                  PROCESSING state + zalo_pay_refund row with the
 *                  reconciliation payload) and the core flow is NOT run:
 *                  total_refunded/qty_refunded stay untouched, the order can
 *                  NOT become CLOSED - the RefundCronjob finalizes on
 *                  confirmed provider SUCCESS;
 *  3c. FAIL      - the provider refusal propagates untouched: nothing is
 *                  persisted, totals untouched;
 *  3d. TRANSPORT - the outcome is UNKNOWN, so the refund is registered
 *                  pending from the carried tracking outcome (reconciled by
 *                  m_refund_id - never re-requested) and an honest error is
 *                  shown; the cron reconciles.
 *
 * The provider call runs BEFORE CreditmemoService::refund opens its
 * database transaction: no provider I/O inside a DB transaction.
 */
class CreditmemoRefundPlugin
{
    /**
     * @param MethodInterface $method ZaloPayFacade (injected via di.xml).
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param RefundCommand $refundCommand
     * @param PendingRefundManager $pendingRefundManager
     * @param RefundOutcomeMarker $outcomeMarker
     * @param ManagerInterface $messageManager
     * @param CreditmemoRefundPreflight $preflight Magento refund validation
     *        that runs BEFORE the provider call (corrective round 2).
     */
    public function __construct(
        private readonly MethodInterface           $method,
        private readonly PaymentDataObjectFactory  $paymentDataObjectFactory,
        private readonly RefundCommand             $refundCommand,
        private readonly PendingRefundManager      $pendingRefundManager,
        private readonly RefundOutcomeMarker       $outcomeMarker,
        private readonly ManagerInterface          $messageManager,
        private readonly CreditmemoRefundPreflight $preflight
    ) {
    }

    /**
     * @param CreditmemoService $subject
     * @param callable $proceed (CreditmemoInterface $creditmemo, bool $offlineRequested): CreditmemoInterface
     * @param CreditmemoInterface $creditmemo
     * @param bool $offlineRequested
     * @return CreditmemoInterface
     * @throws LocalizedException On every path that must NOT run the core
     *         refund accounting (offline, in-flight duplicate, provider
     *         refusal, untrackable transport failure).
     */
    public function aroundRefund(
        CreditmemoService   $subject,
        callable            $proceed,
        CreditmemoInterface $creditmemo,
        $offlineRequested = false
    ) {
        $order = $creditmemo->getOrder();
        $payment = $order->getPayment();
        if ($payment === null || $payment->getMethod() !== $this->method->getCode()) {
            // Not a ZaloPay refund: the core flow is untouched.
            return $proceed($creditmemo, $offlineRequested);
        }

        if ((bool)$offlineRequested) {
            // An offline refund would finalize Magento's accounting without
            // ever telling the provider - the money would stay at ZaloPay.
            throw new LocalizedException(
                __('Zalopay: Offline refunds are not supported. Please refund online so the provider is informed.')
            );
        }

        $orderId = (int)$order->getId();
        if ($this->pendingRefundManager->hasInFlight($orderId)) {
            throw new LocalizedException(
                __('Zalopay: A previous refund for this order is still being reconciled with the provider. Please wait until it is finalized before requesting another refund.')
            );
        }

        // MAGENTO VALIDATION BEFORE PROVIDER (corrective round 2): the core
        // refund validation (CreditmemoService::validateForRefund - protected,
        // mirrored by the preflight service) must PASS before any provider
        // I/O. An invalid Magento refund (over-refund, already-processed
        // credit memo, missing order, non-positive amount) is rejected here
        // - the provider is never asked, and nothing is persisted.
        $this->preflight->validateRefundable($creditmemo);

        // The capture (invoice) transaction identifies the provider payment:
        // Payment::refund sets parentTransactionId only inside its own flow,
        // which we preempt - pin it here so the request builder can resolve
        // zp_trans_id. An online ZaloPay refund always has an invoice.
        $parentTxnId = (string)($creditmemo->getInvoice()?->getTransactionId() ?? '');
        if ($parentTxnId === '') {
            throw new LocalizedException(
                __('Zalopay: The original invoice transaction for this refund cannot be found. The credit memo cannot be refunded.')
            );
        }

        $payment->setCreditmemo($creditmemo);
        $payment->setParentTransactionId($parentTxnId);
        $commandSubject = [
            'payment' => $this->paymentDataObjectFactory->create($payment),
            'amount'  => (float)$creditmemo->getBaseGrandTotal(),
        ];

        try {
            $outcome = $this->refundCommand->execute($commandSubject);
        } catch (RefundTransportException $exception) {
            $trackOutcome = $exception->getOutcome();
            if ($trackOutcome === null) {
                // The request identity was never built: nothing durable to
                // track - surface a retryable failure (logged upstream).
                throw new LocalizedException(
                    __('Zalopay: Refund failed. Please try again later.'),
                    $exception
                );
            }

            // Outcome UNKNOWN: track durably and reconcile by m_refund_id.
            $this->pendingRefundManager->registerPending(
                $creditmemo,
                $trackOutcome,
                PendingRefundManager::EVIDENCE_TRANSPORT
                . 'initial refund outcome unknown - reconciliation pending'
            );

            throw new LocalizedException(
                __('Zalopay: Refund status could not be confirmed. The refund is tracked and will be reconciled automatically.'),
                $exception
            );
        }

        if ($outcome === null) {
            // Defensive: the marker claims the provider was already asked for
            // this order but this plugin did not ask - hand over to the core
            // flow unchanged.
            return $proceed($creditmemo, false);
        }

        if ($outcome->getStatus() === RefundOutcome::STATUS_PROCESSING) {
            // Provider accepted, money not yet refunded: persist the durable
            // pending track and STOP - the core refund accounting must NOT
            // run (invariant: PROCESSING != refunded).
            $this->pendingRefundManager->registerPending($creditmemo, $outcome);
            $this->messageManager->addSuccessMessage(
                __('Zalopay: Refund accepted by the provider and is being processed. The credit memo will be finalized automatically.')
            );

            return $creditmemo;
        }

        // Confirmed SUCCESS: the money is refunded at the provider - the
        // native core flow owns the Magento accounting (exactly once: the
        // gateway refund command skips its provider call via the marker).
        $this->outcomeMarker->markProviderAlreadyAsked($orderId);

        return $proceed($creditmemo, false);
    }
}
