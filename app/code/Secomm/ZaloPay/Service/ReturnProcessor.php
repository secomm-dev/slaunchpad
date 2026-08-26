<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Helper\Data as ZaloPayHelper;
use Secomm\ZaloPay\Logger\Logger;

/**
 * Return (browser redirect) processing for the payment-first flow.
 *
 * The browser redirect is NOT payment proof. Verification order:
 *  1. attempt lookup by apptransid (no order assumptions);
 *  2. duplicate return on FINALIZED -> success, no state change;
 *  3. best-effort key2 checksum on the redirect params (when present);
 *  4. provider status != paid -> explicit FAILED transition (or notice when
 *     still processing);
 *  5. authoritative v2/query server-side verification;
 *  6. amount lock: provider-confirmed amount vs the PERSISTED attempt
 *     snapshot — never re-converted through FX. Mismatch = PAID + explicit
 *     error for reconciliation, never auto-placed;
 *  7. idempotent order placement + capture via OrderFinalizer.
 */
class ReturnProcessor
{
    /**
     * ZaloPay return callback status: 1 = paid, anything else = not paid.
     */
    private const STATUS_PAID = 1;

    /**
     * v2/query return codes: 3 = unpaid / still processing.
     */
    private const QUERY_PROCESSING = 3;

    /**
     * ReturnProcessor constructor.
     *
     * @param PaymentAttemptRepositoryInterface $repository
     * @param CommandPoolInterface $commandPool
     * @param OrderFinalizer $orderFinalizer
     * @param ZaloPayHelper $helperData
     * @param Logger $logger
     */
    public function __construct(
        private readonly PaymentAttemptRepositoryInterface $repository,
        private readonly CommandPoolInterface             $commandPool,
        private readonly OrderFinalizer                   $orderFinalizer,
        private readonly ZaloPayHelper                    $helperData,
        private readonly Logger                           $logger
    ) {
    }

    /**
     * Process the ZaloPay return redirect.
     *
     * @param array $params Raw GET params from the redirect.
     * @return string Redirect path for the controller.
     * @throws LocalizedException Always with a customer-safe message on failure.
     */
    public function process(array $params): string
    {
        $appTransId = trim((string)($params['apptransid'] ?? $params['app_trans_id'] ?? ''));
        if ($appTransId === '') {
            throw new LocalizedException(__('Invalid ZaloPay return payload.'));
        }

        $attempt = $this->repository->getByAppTransId($appTransId);
        if ($attempt === null) {
            $this->logger->warning('ZaloPay return: no payment attempt found.', ['app_trans_id' => $appTransId]);
            throw new LocalizedException(__('ZaloPay payment session not found. Please contact support.'));
        }

        // Duplicate return after a finalized attempt: same resulting state.
        if ($attempt->getPaymentStatus() === PaymentAttemptInterface::STATUS_FINALIZED) {
            return 'checkout/onepage/success';
        }

        $status = (int)($params['status'] ?? 0);

        // Best-effort tamper check: the hosted page signs its redirect params
        // with key2. Authoritative proof comes from v2/query below.
        if (!empty($params['checksum']) && !$this->helperData->verifyRedirect($params)) {
            $this->logger->error(
                'ZaloPay return checksum mismatch.',
                ['app_trans_id' => $appTransId, 'status' => $status]
            );
            throw new LocalizedException(__('Transaction verification failed.'));
        }

        if ($status !== self::STATUS_PAID) {
            if ($attempt->canTransitionTo(PaymentAttemptInterface::STATUS_FAILED)) {
                $attempt->markFailed(sprintf('ZaloPay return status %d.', $status), 'cancelled');
                $this->repository->save($attempt);
            }
            throw new LocalizedException(__('Your ZaloPay payment was not completed.'));
        }

        // Authoritative server-side verification.
        $query = $this->queryTransaction($appTransId);
        $returnCode = (int)($query[AbstractResponseValidator::RETURN_CODE] ?? 0);

        if ($returnCode === self::QUERY_PROCESSING) {
            // Provider still processing: attempt stays ACTIVE (TTL, IPN or
            // the Phase 2 reconciliation cron resolve it).
            throw new LocalizedException(
                __('Your ZaloPay payment is still being processed. Please check back shortly.')
            );
        }
        if ($returnCode !== AbstractResponseValidator::RETURN_CODE_ACCEPT) {
            if ($attempt->canTransitionTo(PaymentAttemptInterface::STATUS_FAILED)) {
                $attempt->markFailed(sprintf('v2/query return_code %d.', $returnCode), 'failed');
                $this->repository->save($attempt);
            }
            throw new LocalizedException(__('Your ZaloPay payment was not completed.'));
        }

        // Amount lock: provider-confirmed amount vs the persisted snapshot.
        $paidAmount = (int)($query[AbstractResponseValidator::TOTAL_AMOUNT] ?? 0);
        $zpTransId = (string)($query[AbstractResponseValidator::ZP_TRANS_ID] ?? '');
        if ($paidAmount !== (int)$attempt->getAmount()) {
            $this->handleAmountMismatch($attempt, $paidAmount, $appTransId, $zpTransId);
        }

        // v2/query confirmation IS the payment proof: make the PAID
        // transition explicit (unless the IPN already recorded it) before
        // the finalizer takes over.
        if ($attempt->canTransitionTo(PaymentAttemptInterface::STATUS_PAID)) {
            $attempt->markPaid($zpTransId !== '' ? $zpTransId : null);
            $this->repository->save($attempt);
        }
        $this->orderFinalizer->finalize($attempt, $zpTransId);

        return 'checkout/onepage/success';
    }

    /**
     * @param string $appTransId
     * @return array
     * @throws LocalizedException
     */
    private function queryTransaction(string $appTransId): array
    {
        try {
            $result = $this->commandPool->get('query_transaction')->execute(
                [AbstractResponseValidator::TRANSACTION_ID => $appTransId]
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'ZaloPay v2/query failed for return verification: ' . $e->getMessage(),
                ['app_trans_id' => $appTransId]
            );
            throw new LocalizedException(
                __('ZaloPay payment could not be verified right now. Please try again or contact support.')
            );
        }

        return $result->get();
    }

    /**
     * Provider holds the money for a different amount than the persisted
     * snapshot: mark PAID (eligible for Phase 2 reconciliation), record the
     * exact mismatch, ack as a customer-visible failure. Never auto-place.
     *
     * @param PaymentAttemptInterface $attempt
     * @param int $paidAmount
     * @param string $appTransId
     * @param string $zpTransId
     * @return void
     * @throws LocalizedException
     */
    private function handleAmountMismatch(
        PaymentAttemptInterface $attempt,
        int $paidAmount,
        string $appTransId,
        string $zpTransId
    ): void
    {
        $this->logger->critical(
            'ZaloPay amount mismatch: refusing automatic order placement.',
            [
                'app_trans_id' => $appTransId,
                'paid_amount' => $paidAmount,
                'snapshot_amount' => (int)$attempt->getAmount(),
                'attempt_id' => $attempt->getEntityId(),
            ]
        );
        if ($attempt->canTransitionTo(PaymentAttemptInterface::STATUS_PAID)) {
            $attempt->markPaid($zpTransId !== '' ? $zpTransId : null);
        }
        $attempt->setLastError(
            sprintf('Amount mismatch: paid %d, snapshot %d.', $paidAmount, (int)$attempt->getAmount())
        );
        $this->repository->save($attempt);
        throw new LocalizedException(
            __('Payment amount mismatch detected. Please contact support with reference %1.', $appTransId)
        );
    }
}
