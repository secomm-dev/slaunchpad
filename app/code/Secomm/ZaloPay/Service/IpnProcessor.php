<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Service;

use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Gateway\Helper\Authorization;
use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Logger\Logger;

/**
 * IPN (server-to-server callback) processing for the payment-first flow.
 *
 * Lookup is attempt-first: app_trans_id -> payment attempt -> (optional)
 * order. An IPN arriving BEFORE the customer returns (order not yet placed)
 * is a VALID lifecycle state: the attempt is marked PAID with the provider
 * transaction id; the Return redirect (or the Phase 2 reconciliation cron)
 * then finalizes the order. Phase 1 never places orders from the IPN
 * context — callbacks stay stateless and re-runnable:
 *  - duplicate IPN on FINALIZED -> same resulting state, acknowledged;
 *  - MAC failure -> 500 so ZaloPay retries;
 *  - amount mismatch -> PAID + explicit error for reconciliation (ack).
 */
class IpnProcessor
{
    /**
     * IPNProcessor constructor.
     *
     * @param PaymentAttemptRepositoryInterface $repository
     * @param Authorization $authorization
     * @param Logger $logger
     */
    public function __construct(
        private readonly PaymentAttemptRepositoryInterface $repository,
        private readonly Authorization                     $authorization,
        private readonly Logger                            $logger
    ) {
    }

    /**
     * Process an IPN for the payment-first flow.
     *
     * @param array $response Parsed callback payload (with trans_data already decoded).
     * @return array|null {'http_code': int, 'errors': bool, 'messages': string} —
     * null when the payload does not reference a payment attempt (caller falls
     * back to the legacy order-first flow).
     */
    public function process(array $response): ?array
    {
        $dataString = (string)($response['data'] ?? '');
        $transData = is_array($response['trans_data'] ?? null) ? $response['trans_data'] : [];
        $appTransId = (string)($transData[AbstractDataBuilder::APP_TRANS_ID] ?? '');
        if ($appTransId === '') {
            return null;
        }

        $attempt = $this->repository->getByAppTransId($appTransId);
        if ($attempt === null) {
            return null; // No attempt: legacy order-first callback.
        }

        // Authoritative MAC (key2 over the raw `data` string) — required.
        if ($dataString === '' || empty($response['mac'])
            || !hash_equals($this->authorization->getMacKey2($dataString), (string)$response['mac'])
        ) {
            $this->logger->error('ZaloPay IPN MAC verification failed.', ['app_trans_id' => $appTransId]);

            return ['http_code' => 500, 'errors' => true, 'messages' => 'MAC verification failed.'];
        }

        $zpTransId = (string)($transData[AbstractResponseValidator::ZP_TRANS_ID] ?? '');
        $paidAmount = (int)($transData[AbstractResponseValidator::TOTAL_AMOUNT] ?? 0);

        if ($attempt->getPaymentStatus() === PaymentAttemptInterface::STATUS_FINALIZED) {
            // Duplicate IPN: same resulting state, acknowledge without changes.
            return ['http_code' => 200, 'errors' => false, 'messages' => 'Success'];
        }

        // Amount lock against the persisted snapshot (never re-convert FX).
        if ($paidAmount !== 0 && $paidAmount !== (int)$attempt->getAmount()) {
            $this->logger->critical(
                'ZaloPay IPN amount mismatch: recorded for reconciliation, no order placed.',
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
                sprintf('IPN amount mismatch: paid %d, snapshot %d.', $paidAmount, (int)$attempt->getAmount())
            );
            $this->repository->save($attempt);

            return ['http_code' => 200, 'errors' => false, 'messages' => 'Recorded for reconciliation.'];
        }

        if (!$attempt->canTransitionTo(PaymentAttemptInterface::STATUS_PAID)) {
            // e.g. STALE/EXPIRED/FAILED attempt whose callback arrived late:
            // log for reconciliation, acknowledge (retrying cannot fix it).
            $this->logger->error(
                'ZaloPay IPN paid callback on non-payable attempt state.',
                [
                    'app_trans_id' => $appTransId,
                    'payment_status' => $attempt->getPaymentStatus(),
                    'attempt_id' => $attempt->getEntityId(),
                ]
            );

            return ['http_code' => 200, 'errors' => false, 'messages' => 'Recorded for reconciliation.'];
        }

        $attempt->markPaid($zpTransId !== '' ? $zpTransId : null);
        $this->repository->save($attempt);
        $this->logger->info(
            sprintf(
                'ZaloPay IPN marked attempt #%d PAID (order placement deferred to Return/cron).',
                (int)$attempt->getEntityId()
            )
        );

        return ['http_code' => 200, 'errors' => false, 'messages' => 'Success'];
    }
}
