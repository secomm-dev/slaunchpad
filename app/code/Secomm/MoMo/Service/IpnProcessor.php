<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\MoMo\Service;

use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;
use Secomm\MoMo\Api\PaymentAttemptRepositoryInterface;
use Secomm\MoMo\Exception\ContractMismatchException;
use Secomm\MoMo\Gateway\Validator\NotifyValidator;

/**
 * MoMo IPN (server-to-server Notify) processing for the payment-first flow
 * (MOMO-01).
 *
 * DOMAIN OUTCOMES ONLY: this class never invents an HTTP body. It returns
 * one of the OUTCOME_* constants and the Notify controller serializes it
 * into the MoMo-expected JSON contract:
 *
 *  - SUCCESS            -> HTTP 200 {"resultCode": 0} (paid + order finalized).
 *  - ACK_RECONCILIATION -> HTTP 200 {"resultCode": 0}: the Notify is valid
 *    and its evidence is deterministically persisted (amount mismatch,
 *    quarantined conflict, contract refusal, late-PAID on a terminal
 *    attempt) — acknowledging STOPS useless MoMo retries; no order follows,
 *    manual reconciliation resolves it.
 *  - INVALID_CALLBACK   -> HTTP 200 {"resultCode": 1}: signature failure,
 *    unparseable/malformed payload or a valid payload for a DIFFERENT
 *    merchant identity — nothing to mutate, not retryable.
 *  - UNKNOWN_REFERENCE  -> HTTP 404: a well-formed orderId that matches NO
 *    attempt row (deleted/foreign traffic) — nothing to mutate.
 *  - RETRYABLE_FAILURE  -> HTTP 500 {"resultCode": 1}: transient technical
 *    failure during finalization — MoMo's documented IPN retry policy is
 *    the AC9 recovery driver (no local recovery cron in this scope).
 *
 * The MoMo IPN IS the authoritative success path (spec §4.3.3): the
 * HMAC-SHA256 signature over the fixed 13-field order — which only MoMo's
 * secret key can produce — plus the merchant-identity and
 * transaction-identity checks in NotifyValidator (partnerCode, orderId/
 * requestId/extraData echoes, frozen amount) are sufficient proof. No
 * additional v2/query is required, and no MoMo HTTP runs inside any DB
 * transaction.
 *
 * STRICT signed-value parsing: a malformed `amount`/`transId` inside a
 * signature-valid payload is a provider anomaly — answered INVALID with
 * ZERO mutation (a malformed string is NEVER cast to a number that could
 * sneak past the amount check). Automatic finalization requires a POSITIVE
 * transId: a success resultCode without one quarantines the verified money
 * (provider_transaction_unavailable) — never an order.
 *
 * The canonical lifecycle runs through PaymentAttemptLifecycle (locked,
 * short transaction, fresh-row decisions) and finalization through
 * OrderFinalizer. This class has NO session dependency and never touches one.
 */
class IpnProcessor
{
    /** Payment processed and order finalized — HTTP 200 resultCode 0. */
    public const OUTCOME_SUCCESS = 'success';

    /** Valid callback whose evidence is persisted; retries are useless — HTTP 200 resultCode 0. */
    public const OUTCOME_ACK_RECONCILIATION = 'reconciliation_ack';

    /** Invalid/unverifiable callback (signature, payload, identity) — HTTP 200 resultCode 1. */
    public const OUTCOME_INVALID_CALLBACK = 'invalid_callback';

    /** Well-formed reference that matches no attempt — HTTP 404. */
    public const OUTCOME_UNKNOWN_REFERENCE = 'unknown_reference';

    /** Transient failure — HTTP 500 (MoMo retries per its documented IPN policy). */
    public const OUTCOME_RETRYABLE_FAILURE = 'retryable_failure';

    /**
     * Sentinel for a present-but-malformed signed field: a non-integer value
     * inside a signature-valid payload is a provider anomaly — NEVER cast to
     * zero, which would sneak it past the amount check.
     */
    private const FIELD_MALFORMED = false;

    /**
     * IpnProcessor constructor.
     *
     * @param PaymentAttemptRepositoryInterface $repository
     * @param NotifyValidator $notifyValidator
     * @param OrderFinalizer $orderFinalizer
     * @param PaymentAttemptLifecycle $lifecycle
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PaymentAttemptRepositoryInterface $repository,
        private readonly NotifyValidator $notifyValidator,
        private readonly OrderFinalizer $orderFinalizer,
        private readonly PaymentAttemptLifecycle $lifecycle,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Process a MoMo IPN (Notify) payload.
     *
     * @param array $response Raw parsed Notify payload (13 signed fields + signature).
     * @return string One of the OUTCOME_* constants — the controller
     *         serializes it into the HTTP/JSON MoMo contract.
     */
    public function process(array $response): string
    {
        $orderRef = trim((string)($response['orderId'] ?? ''));
        if ($orderRef === '') {
            $this->logger->error('MoMo IPN rejected: no orderId in the Notify payload.');

            return self::OUTCOME_INVALID_CALLBACK;
        }

        $attempt = $this->repository->getByOrderRef($orderRef);
        if ($attempt === null) {
            // Unknown reference: nothing to mutate — the Notify controller
            // answers 404 so MoMo stops retrying a dead reference.
            $this->logger->error(
                'MoMo IPN: no payment attempt found for the callback reference.',
                ['order_ref' => $orderRef]
            );

            return self::OUTCOME_UNKNOWN_REFERENCE;
        }

        // STRICT parse of the signed values BEFORE any state consideration —
        // a malformed string inside a signature-valid payload is a provider
        // anomaly, never cast into a passing value.
        $amount = $this->parseStrictInt($response['amount'] ?? null);
        $transId = $this->parseStrictTransId($response['transId'] ?? null);
        if ($amount === self::FIELD_MALFORMED || $transId === self::FIELD_MALFORMED) {
            $this->logger->critical(
                'MoMo IPN rejected: malformed signed amount/transId in a signature-valid payload.',
                ['order_ref' => $orderRef]
            );

            return self::OUTCOME_INVALID_CALLBACK;
        }

        // Full authoritative chain: 13-field signature + partnerCode +
        // orderId/requestId/extraData echoes + frozen amount. NOTHING
        // mutates before this passes.
        $result = $this->notifyValidator->validate(['response' => $response, 'attempt' => $attempt]);
        if (!$result->isValid()) {
            $this->logger->error(
                'MoMo IPN rejected: signature/identity/amount validation failed.',
                [
                    'order_ref' => $orderRef,
                    'messages' => array_map('strval', (array)$result->getFailsDescription()),
                ]
            );

            return self::OUTCOME_INVALID_CALLBACK;
        }

        $resultCode = isset($response['resultCode']) ? (int)$response['resultCode'] : -1;
        if ($resultCode !== 0) {
            // Signature-valid authoritative FAILURE (cancelled/expired/failed
            // at MoMo): transition where the fresh state permits, never
            // regress money-real state, acknowledge so MoMo stops retrying.
            return $this->recordAuthoritativeFailureAndAcknowledge(
                $orderRef,
                $resultCode,
                (string)($response['message'] ?? '')
            );
        }

        // resultCode 0 (paid): a positive transId is REQUIRED for automatic
        // finalization — verified money without a provable provider identity
        // is quarantined, never ordered.
        if ($transId === null) {
            $this->logger->critical(
                'MoMo IPN: success result without a positive transId; quarantined for reconciliation, '
                . 'no order placed.',
                ['order_ref' => $orderRef]
            );
            $this->lifecycle->recordProviderIdentityUnavailable($orderRef, 'IPN');

            return self::OUTCOME_ACK_RECONCILIATION;
        }

        return $this->applyVerifiedPaidAndFinalize($orderRef, (string)$transId);
    }

    /**
     * STRICT parse of the signed `amount`: integer grammar only. NULL when
     * the field is absent (the validator answers the amount mismatch);
     * FIELD_MALFORMED when present but not an integer (caller answers
     * INVALID, zero mutation).
     *
     * @param mixed $raw
     * @return int|null|false
     */
    private function parseStrictInt(mixed $raw)
    {
        if ($raw === null) {
            return null;
        }
        if (is_bool($raw) || is_array($raw) || !is_numeric($raw)
            || preg_match('/^-?\d+$/', trim((string)$raw)) !== 1
        ) {
            return self::FIELD_MALFORMED;
        }

        return (int)$raw;
    }

    /**
     * STRICT parse of the signed `transId`: only a POSITIVE integer proves
     * the provider identity; zero/negative/absent degrades to NULL (the
     * caller quarantines verified money without identity — the safe
     * direction); a present non-integer value is FIELD_MALFORMED.
     *
     * @param mixed $raw
     * @return string|null|false The canonical decimal id, NULL when
     *         absent/zero, FALSE when malformed.
     */
    private function parseStrictTransId(mixed $raw)
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_bool($raw) || is_array($raw) || !is_numeric($raw)
            || preg_match('/^-?\d+$/', trim((string)$raw)) !== 1
        ) {
            return self::FIELD_MALFORMED;
        }
        $int = (int)$raw;

        return $int > 0 ? (string)$int : null;
    }

    /**
     * Signature-valid authoritative failure: transition where the fresh
     * state permits, keep money-real conflicts, acknowledge — retries
     * cannot change an authoritative result.
     *
     * @param string $orderRef
     * @param int $resultCode
     * @param string $message
     * @return string
     */
    private function recordAuthoritativeFailureAndAcknowledge(
        string $orderRef,
        int $resultCode,
        string $message
    ): string {
        $fresh = $this->lifecycle->recordVerifiedFailure(
            $orderRef,
            sprintf('MoMo resultCode %d (%s).', $resultCode, $message),
            'failed'
        );

        if ($fresh->getPaymentStatus() === PaymentAttemptInterface::STATUS_FINALIZED) {
            // Finalized earlier on prior authoritative proof — the order
            // exists; recover the binding, acknowledge success.
            try {
                $this->orderFinalizer->finalizeOrRecover($fresh);
            } catch (ContractMismatchException $exception) {
                return self::OUTCOME_ACK_RECONCILIATION;
            } catch (\Exception $exception) {
                return self::OUTCOME_RETRYABLE_FAILURE;
            }

            return self::OUTCOME_SUCCESS;
        }
        if ($fresh->getPaymentStatus() === PaymentAttemptInterface::STATUS_PAID) {
            $this->logger->critical(
                'MoMo IPN: authoritative failure conflicts with recorded PAID; kept money-real.',
                ['order_ref' => $orderRef, 'result_code' => $resultCode]
            );
        }

        return self::OUTCOME_ACK_RECONCILIATION;
    }

    /**
     * Authoritative success: canonical lifecycle PAID transition on the
     * LOCKED fresh row, then the single finalization boundary.
     *
     * @param string $orderRef
     * @param string $transId
     * @return string
     */
    private function applyVerifiedPaidAndFinalize(string $orderRef, string $transId): string
    {
        // Idempotent for PAID/FINALIZED, evidence-only for terminal unpaid
        // states, quarantining for a conflicting transId.
        $fresh = $this->lifecycle->recordVerifiedPaid($orderRef, $transId);

        if ($fresh->getRequiresReconciliation()) {
            // Conflicting provider identity or mismatch evidence — never
            // auto-finalize.
            $this->logger->critical(
                'MoMo IPN: attempt quarantined; no automatic order.',
                [
                    'order_ref' => $orderRef,
                    'reconciliation_code' => (string)$fresh->getReconciliationCode(),
                ]
            );

            return self::OUTCOME_ACK_RECONCILIATION;
        }

        $status = $fresh->getPaymentStatus();
        if ($status !== PaymentAttemptInterface::STATUS_PAID
            && $status !== PaymentAttemptInterface::STATUS_FINALIZED
        ) {
            // e.g. STALE/EXPIRED/FAILED attempt whose paid IPN arrived late:
            // evidence + provider identity persisted, no order, acknowledge
            // (retrying cannot fix it).
            return self::OUTCOME_ACK_RECONCILIATION;
        }

        // Canonical finalization NOW — server-to-server, no browser needed.
        // PAID (fresh or from an earlier failed finalize) is retryable;
        // FINALIZED recovers the bound order (duplicate IPN idempotent).
        try {
            $this->orderFinalizer->finalizeOrRecover($fresh, $transId);
        } catch (ContractMismatchException $exception) {
            // Deterministic: the money is real but the current quote/order
            // contract cannot be honoured. The lifecycle quarantined the
            // attempt with the evidence. Acknowledge to stop MoMo retries
            // of an unfixable callback.
            return self::OUTCOME_ACK_RECONCILIATION;
        } catch (\Exception $exception) {
            // Transient technical failure — the attempt remains PAID (the
            // finalizer's transaction rolled back). HTTP 500 asks MoMo to
            // retry per its documented IPN policy (the AC9 recovery driver).
            $this->logger->critical(
                sprintf(
                    'MoMo IPN finalization failed (retryable), attempt #%d: %s',
                    (int)$fresh->getEntityId(),
                    $exception->getMessage()
                )
            );

            return self::OUTCOME_RETRYABLE_FAILURE;
        }

        return self::OUTCOME_SUCCESS;
    }
}
