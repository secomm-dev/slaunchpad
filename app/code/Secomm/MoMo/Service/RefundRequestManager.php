<?php
/**
 * Orchestrates durable refund-request identity and the submission guards.
 *
 * Owns: stale-pending sweep, the open-row duplicate-submission block, the
 * budget drift guard, identity minting + row insertion, and guarded outcome
 * transitions. All persistence is on the independent connection, so a
 * FAILED/UNKNOWN row survives the CreditmemoService rollback that aborts
 * the creditmemo (the guard evidence MUST outlive the native accounting
 * rollback).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Secomm\MoMo\Api\Data\RefundRequestInterface;
use Secomm\MoMo\Api\RefundRequestRepositoryInterface;
use Secomm\MoMo\Gateway\Request\RefundBuilder;
use Secomm\MoMo\Model\OrderRefBuilder;
use Secomm\MoMo\Model\RefundRequestFactory;

class RefundRequestManager
{
    /**
     * Pending rows older than this are swept to unknown: the process died
     * mid-request and the provider outcome can no longer be confirmed from
     * this request. Must stay well above the refund HTTP timeout (45s).
     */
    public const STALE_PENDING_SECONDS = 600;

    /**
     * @var RefundRequestRepositoryInterface
     */
    private RefundRequestRepositoryInterface $repository;

    /**
     * @var RefundRequestFactory
     */
    private RefundRequestFactory $factory;

    /**
     * @var OrderRefBuilder
     */
    private OrderRefBuilder $orderRefBuilder;

    /**
     * RefundRequestManager constructor.
     *
     * @param RefundRequestRepositoryInterface $repository
     * @param RefundRequestFactory $factory
     * @param OrderRefBuilder $orderRefBuilder
     */
    public function __construct(
        RefundRequestRepositoryInterface $repository,
        RefundRequestFactory $factory,
        OrderRefBuilder $orderRefBuilder
    ) {
        $this->repository = $repository;
        $this->factory = $factory;
        $this->orderRefBuilder = $orderRefBuilder;
    }

    /**
     * Run the pre-submission guards and create the pending refund row with
     * its minted provider identity.
     *
     * Throws (with an admin-safe message) when the submission must be
     * blocked; the throw happens BEFORE any provider call, so a blocked
     * refund never sends money instructions twice.
     *
     * @param PaymentDataObjectInterface $paymentDO
     * @param int $amount Refund amount (same base-currency read the builder uses).
     * @return RefundRequestInterface The persisted pending row.
     * @throws LocalizedException
     */
    public function openIdentity(PaymentDataObjectInterface $paymentDO, int $amount): RefundRequestInterface
    {
        $identity = $this->readPaymentIdentity($paymentDO);
        $orderRef = $identity['order_ref'];
        $transId = $identity['trans_id'];

        // 1. Stale-pending sweep: relabel orphaned in-flight rows so they
        //    report accurately (they stay blocking — only resolution clears).
        $this->repository->sweepStalePending($orderRef, $transId, self::STALE_PENDING_SECONDS);

        // 2. Open-row block: at most ONE open refund per payment (AC3/AC6).
        $open = $this->repository->findOpenByPaymentIdentity($orderRef, $transId);
        if ($open !== null) {
            if ($open->getStatus() === RefundRequestInterface::STATUS_PENDING) {
                throw new LocalizedException(
                    __(
                        'A MoMo refund for this payment is already in progress (request %1).'
                        . ' Wait for it to complete before issuing another refund.',
                        $open->getRequestId()
                    )
                );
            }

            throw new LocalizedException(
                __(
                    'An earlier MoMo refund has an unconfirmed outcome (request %1, status unknown).'
                    . ' Resolve it first: bin/magento momo:refund:resolve %1',
                    $open->getRequestId()
                )
            );
        }

        // 3. Budget drift guard: provider-executed refunds exceeding the
        //    accounting total mean the books must be realigned first (e.g.
        //    an UNKNOWN resolved to SUCCESS after its creditmemo rolled back).
        $amountRefunded = (int)round((float)$paymentDO->getPayment()->getAmountRefunded());
        $successfulTotal = $this->repository->getSuccessfulTotal($orderRef, $transId);
        if ($successfulTotal > $amountRefunded) {
            throw new LocalizedException(
                __(
                    'MoMo refund evidence (%1) exceeds the refunded accounting total (%2).'
                    . ' Realign the accounting (e.g. offline credit memo) before refunding again.',
                    $successfulTotal,
                    $amountRefunded
                )
            );
        }

        // 4. Mint identity once per logical operation; the row IS the
        //    operation (the creditmemo id does not exist at gateway time).
        $refund = $this->factory->create();
        $refund->setOrderId($paymentDO->getOrder()->getId());
        $refund->setOrderIncrementId($paymentDO->getOrder()->getOrderIncrementId());
        $refund->setInvoiceId($this->readInvoiceId($paymentDO));
        $refund->setMomoOrderRef($orderRef);
        $refund->setMomoTransId($transId);
        $refund->setRefundOrderId($this->orderRefBuilder->buildRefundOrderId($orderRef));
        $refund->setRequestId($this->orderRefBuilder->buildRefundRequestId($orderRef));
        $refund->setAmount($amount);
        $refund->setCurrency('VND');
        $refund->setStatus(RefundRequestInterface::STATUS_PENDING);
        $refund->setStoreId((int)$paymentDO->getOrder()->getStoreId());

        // 5. Insert; the UNIQUE(open) key is the hard guard against a race
        //    that slipped past the open-row check above.
        try {
            $this->repository->insert($refund);
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('A MoMo refund for this payment was opened concurrently; refund not sent.')
            );
        }

        return $refund;
    }

    /**
     * Apply a classification to the row via guarded transitions.
     *
     * Idempotent at the SQL level: only the first writer moves an open row
     * to a terminal verdict, so concurrent commands and resolve re-runs
     * cannot overwrite evidence.
     *
     * @param RefundRequestInterface $refund
     * @param RefundClassification $classification
     * @param string|null $transportError Sanitized transport detail (UNKNOWN path).
     * @return bool Whether this call changed the row's state.
     */
    public function recordOutcome(
        RefundRequestInterface $refund,
        RefundClassification $classification,
        ?string $transportError = null
    ): bool {
        $refund->setResponseCode($classification->responseCode);
        $refund->setResponseMessage($classification->responseMessage);
        $refund->setClassificationReason($classification->reason);
        $refund->setProviderTransactionId($classification->providerTransactionId);
        $refund->setLastError(
            $classification->status === RefundRequestInterface::STATUS_UNKNOWN ? $transportError : null
        );

        if ($classification->isSuccess() || $classification->status === RefundRequestInterface::STATUS_FAILED) {
            return $this->repository->finalize($refund);
        }

        // UNKNOWN: pending → unknown (relabeled, still blocking). A row that
        // is already unknown stays untouched (resolve re-queries change nothing).
        return $this->repository->markUnknown($refund);
    }

    /**
     * Resolve the payment identity chain (identical semantics to the
     * request builder, so the row and the provider payload can never
     * disagree about the payment being refunded).
     *
     * @param PaymentDataObjectInterface $paymentDO
     * @return array{order_ref: string, trans_id: string}
     * @throws LocalizedException When no transId can be resolved.
     */
    public function readPaymentIdentity(PaymentDataObjectInterface $paymentDO): array
    {
        $payment = $paymentDO->getPayment();
        $orderRef = (string)($payment->getAdditionalInformation(RefundBuilder::KEY_ORDER_REF)
            ?? $paymentDO->getOrder()->getOrderIncrementId());
        $transId = (string)($payment->getAdditionalInformation(RefundBuilder::KEY_TRANS_ID)
            ?? $payment->getAdditionalInformation(RefundBuilder::KEY_TRANS_ID_LEGACY)
            ?? '');
        if ($transId === '') {
            throw new LocalizedException(
                __('MoMo transaction reference is missing; the refund cannot be sent to MoMo.')
            );
        }

        return ['order_ref' => $orderRef, 'trans_id' => $transId];
    }

    /**
     * Read the invoice id from the creditmemo attached to the payment
     * (available before the gateway call; the creditmemo id is not — it is
     * backfilled post-commit by the sales plugin).
     *
     * @param PaymentDataObjectInterface $paymentDO
     * @return int|null
     */
    private function readInvoiceId(PaymentDataObjectInterface $paymentDO): ?int
    {
        $creditmemo = $paymentDO->getPayment()->getCreditmemo();
        $invoice = $creditmemo ? $creditmemo->getInvoice() : null;
        $invoiceId = $invoice ? $invoice->getId() : null;

        return $invoiceId ? (int)$invoiceId : null;
    }
}
