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
use Secomm\ZaloPay\Exception\ContractMismatchException;
use Secomm\ZaloPay\Gateway\Helper\Authorization;
use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Logger\Logger;

/**
 * IPN (server-to-server callback) processing for the payment-first flow.
 *
 * DOMAIN OUTCOMES ONLY (corrective round 3, Blocker 3): this class never
 * invents an HTTP body. It returns one of the OUTCOME_* constants and the
 * controller serializes the EXACT official ZaloPay callback response
 * contract (https://docs.zalopay.vn/docs/specs/callback-api/ + knowledge
 * base "Callback": HTTP 200 JSON `{return_code, return_message}` —
 * `1` = "Success", `2` = "Invalid"; the official sample additionally uses
 * `0` = "callback again (up to 3 times)" for transient failures). The
 * legacy `{errors, messages}` + 404/500 body was NOT the provider protocol.
 *
 *  - SUCCESS            -> return_code 1 "Success" (paid + order finalized).
 *  - ACK_RECONCILIATION -> return_code 1 "Success": the callback is valid
 *    and its evidence is deterministically persisted (amount mismatch,
 *    quarantined conflict, contract refusal, late-PAID on a terminal
 *    attempt) — acknowledging STOPS useless provider retries; no order
 *    follows, manual reconciliation resolves it.
 *  - INVALID_CALLBACK   -> return_code 2 "Invalid": MAC failure, unparseable
 *    payload or unknown app_trans_id — nothing to mutate, not retryable.
 *  - RETRYABLE_FAILURE  -> return_code 0: transient technical failure or a
 *    still-processing payment — ZaloPay retries per its documented policy,
 *    and the bounded recovery worker (15-minute proactive QueryOrder) is
 *    the documented backstop.
 *
 * Provider amount is STRICTLY REQUIRED for payment (corrective round 3,
 * Blocker 4): a missing or zero `amount` in the callback data NEVER means
 * "continue anyway". Strategy: fall back to the AUTHORITATIVE v2/query and
 * require return_code 1 AND the EXACT snapshot amount before any PAID/
 * finalize; anything else is retried or quarantined — never an order.
 *
 * STRICT CALLBACK PAYMENT IDENTITY (corrective round 4, Blocker 1 —
 * https://docs.zalopay.vn/docs/specs/callback-api/):
 *  - the envelope `type` field MUST be 1 (Order callback); Agreement (2)
 *    or unknown/missing types are answered "Invalid" with ZERO mutation —
 *    an Agreement notification must never drive an order lifecycle;
 *  - `data.app_id` is MAC-authenticated (the MAC input is the ENTIRE data
 *    string, keyed with key2 — only ZaloPay can produce a valid payload)
 *    and is ADDITIONALLY compared against the configured app_id: a
 *    mismatch is a configuration/cross-environment error, answered
 *    "Invalid" with zero mutation so payment state is never poisoned
 *    (DEC-TASKEDS9T5-004);
 *  - signed values are strictly parsed (integer grammar, no blind casts):
 *    a MALFORMED `amount`/`zp_trans_id` inside a MAC-valid payload is a
 *    provider anomaly — "Invalid", zero mutation (a malformed string is
 *    NEVER cast to zero to sneak into a fallback branch);
 *  - automatic finalization requires an authoritative POSITIVE
 *    `zp_trans_id`: missing/zero in the callback → the v2/query must prove
 *    return_code 1 + exact amount + positive zp_trans_id; when neither
 *    proof carries a positive id the verified money is quarantined
 *    (provider_transaction_unavailable), never ordered;
 *  - callback zp_trans_id A vs query zp_trans_id B (A ≠ B): provider
 *    transaction conflict — BOTH identities preserved, quarantined;
 *    neither is silently preferred.
 *
 * The canonical lifecycle runs through PaymentAttemptLifecycle (locked,
 * short transaction, fresh-row decisions). A conflicting zp_trans_id (same
 * app_trans_id, different authoritative id) quarantines the attempt — it
 * can never silently auto-finalize (corrective round 3, Blocker 5).
 *
 * This class has NO session dependency and never touches one.
 */
class IpnProcessor
{
    /** Payment processed and order finalized — return_code 1 "Success". */
    public const OUTCOME_SUCCESS = 'success';

    /** Valid callback whose evidence is persisted; retries are useless — return_code 1 "Success". */
    public const OUTCOME_ACK_RECONCILIATION = 'reconciliation_ack';

    /** Invalid/unverifiable callback (MAC, payload, unknown reference) — return_code 2 "Invalid". */
    public const OUTCOME_INVALID_CALLBACK = 'invalid_callback';

    /** Transient failure or still processing — return_code 0 (documented "callback again"). */
    public const OUTCOME_RETRYABLE_FAILURE = 'retryable_failure';

    /**
     * Sentinel for a present-but-malformed signed field (corrective round
     * 4): a non-integer value inside MAC-valid data is a provider anomaly —
     * NEVER cast to zero, which would sneak it into the missing/zero →
     * query-fallback branch.
     */
    private const FIELD_MALFORMED = false;

    /**
     * IPNProcessor constructor.
     *
     * @param PaymentAttemptRepositoryInterface $repository
     * @param Authorization $authorization
     * @param CommandPoolInterface $commandPool
     * @param OrderFinalizer $orderFinalizer
     * @param PaymentAttemptLifecycle $lifecycle
     * @param Logger $logger
     */
    public function __construct(
        private readonly PaymentAttemptRepositoryInterface $repository,
        private readonly Authorization                     $authorization,
        private readonly CommandPoolInterface              $commandPool,
        private readonly OrderFinalizer                    $orderFinalizer,
        private readonly PaymentAttemptLifecycle           $lifecycle,
        private readonly Logger                            $logger
    ) {
    }

    /**
     * Process an IPN for the payment-first flow.
     *
     * @param array $response Parsed callback payload (with trans_data already decoded).
     * @return string One of the OUTCOME_* constants — the controller
     *         serializes it into the official ZaloPay response contract.
     */
    public function process(array $response): string
    {
        // BLOCKER 1a (round 4): only Order callbacks (type 1) drive this
        // lifecycle. The `type` envelope field sits OUTSIDE the signed data
        // string and only ever DISCRIMINATES: Agreement (2), unknown or
        // missing types are answered the documented "Invalid" — no lookup,
        // no mutation, no PAID, no order.
        $type = $response['type'] ?? null;
        if ($type === null || is_bool($type) || is_array($type)
            || !is_numeric((string)$type) || (int)$type !== 1
        ) {
            $this->logger->error(
                'ZaloPay IPN rejected: callback is not an Order notification (type must be 1).',
                ['callback_type' => is_scalar($type) ? (string)$type : 'non-scalar']
            );

            return self::OUTCOME_INVALID_CALLBACK;
        }

        $dataString = (string)($response['data'] ?? '');
        $transData = is_array($response['trans_data'] ?? null) ? $response['trans_data'] : [];
        $appTransId = (string)($transData[AbstractDataBuilder::APP_TRANS_ID] ?? '');
        if ($appTransId === '') {
            $this->logger->error('ZaloPay IPN rejected: no app_trans_id in the callback payload.');

            return self::OUTCOME_INVALID_CALLBACK;
        }

        // Authoritative MAC (key2 over the raw `data` string) — required,
        // before ANY state consideration. Documented "Invalid" answer.
        if ($dataString === '' || empty($response['mac'])
            || !hash_equals($this->authorization->getMacKey2($dataString), (string)$response['mac'])
        ) {
            $this->logger->error('ZaloPay IPN MAC verification failed.', ['app_trans_id' => $appTransId]);

            return self::OUTCOME_INVALID_CALLBACK;
        }

        // BLOCKER 1d (round 4): the MAC already proves the payload was
        // produced by ZaloPay (the whole `data` string — app_id included —
        // is signed with key2). The explicit comparison against the
        // configured app_id is DEFENSIVE: a mismatch means the callback
        // belongs to a different ZaloPay application (configuration or
        // cross-environment error). Answer "Invalid" with ZERO mutation so
        // payment state is never poisoned; the misconfiguration is a
        // human-fixable, critical-logged condition.
        $configuredAppId = $this->authorization->getAppId();
        if ($configuredAppId !== null && $configuredAppId !== ''
            && !$this->callbackAppIdMatches($transData, $configuredAppId)
        ) {
            $this->logger->critical(
                'ZaloPay IPN rejected: callback app_id does not match the configured application.',
                ['app_trans_id' => $appTransId]
            );

            return self::OUTCOME_INVALID_CALLBACK;
        }

        $attempt = $this->repository->getByAppTransId($appTransId);
        if ($attempt === null) {
            // Unknown reference (valid MAC or not): nothing to mutate, not
            // retryable — the documented "Invalid" answer.
            $this->logger->error(
                'ZaloPay IPN: no payment attempt found for the callback reference.',
                ['app_trans_id' => $appTransId]
            );

            return self::OUTCOME_INVALID_CALLBACK;
        }

        // BLOCKER 1 + payload validation (round 4): strict parsing of the
        // signed values — a malformed string is rejected BEFORE any
        // payment-state consideration (never cast to zero).
        $callbackAmount = $this->parseCallbackAmount($transData);
        if ($callbackAmount === self::FIELD_MALFORMED) {
            $this->logger->critical(
                'ZaloPay IPN rejected: malformed signed amount in a MAC-valid payload.',
                ['app_trans_id' => $appTransId]
            );

            return self::OUTCOME_INVALID_CALLBACK;
        }
        $callbackZpTransId = $this->parseCallbackZpTransId($transData);
        if ($callbackZpTransId === self::FIELD_MALFORMED) {
            $this->logger->critical(
                'ZaloPay IPN rejected: malformed signed zp_trans_id in a MAC-valid payload.',
                ['app_trans_id' => $appTransId]
            );

            return self::OUTCOME_INVALID_CALLBACK;
        }

        if ($callbackAmount === null || $callbackAmount <= 0) {
            // Corrective round 3, Blocker 4: a missing/zero amount can NEVER
            // mean "continue anyway". Resolve the authoritative amount via
            // server-to-server v2/query BEFORE any PAID/finalize.
            return $this->verifyByQuery($appTransId, $callbackZpTransId);
        }

        if ($callbackAmount !== (int)$attempt->getAmount()) {
            return $this->recordMismatchAndAcknowledge(
                $appTransId,
                $callbackAmount,
                (string)($callbackZpTransId ?? ''),
                'IPN'
            );
        }

        // Amount verified EXACT. Automatic finalization additionally needs
        // the authoritative positive provider transaction id (round 4,
        // Blocker 1b): missing/zero → the v2/query must prove the identity.
        if ($callbackZpTransId === null) {
            return $this->verifyByQuery($appTransId, null);
        }

        return $this->applyVerifiedPaidAndFinalize($appTransId, $callbackZpTransId);
    }

    /**
     * The authoritative v2/query decides — amount and provider identity.
     *
     * Requires return_code 1 AND the EXACT snapshot amount before any
     * PAID/finalize; processing (3) or a technical failure is retryable;
     * FAIL (2) is an authoritative failure; PAID with a wrong/unavailable
     * amount quarantines the attempt. Never an order without a verified
     * exact amount.
     *
     * Provider identity (round 4, Blockers 1b/1c): the query MUST prove a
     * positive zp_trans_id (either its own or the callback's signed one):
     *  - callback id A vs query id B, A ≠ B → provider_transaction_conflict,
     *    BOTH identities preserved, never auto-finalized;
     *  - no positive id from either proof → verified money quarantined
     *    (provider_transaction_unavailable), never auto-finalized.
     *
     * @param string $appTransId
     * @param string|null $callbackZpTransId The callback's STRICTLY parsed
     *        zp_trans_id (null = absent/zero/malformed-rejected upstream).
     * @return string One of the OUTCOME_* constants.
     */
    private function verifyByQuery(string $appTransId, ?string $callbackZpTransId): string
    {
        try {
            $query = $this->queryTransaction($appTransId);
        } catch (LocalizedException $exception) {
            $this->logger->error(
                'ZaloPay IPN: callback amount missing/zero and the authoritative query failed; retryable.',
                ['app_trans_id' => $appTransId, 'reason' => $exception->getMessage()]
            );

            return self::OUTCOME_RETRYABLE_FAILURE;
        }

        $returnCode = (int)($query[AbstractResponseValidator::RETURN_CODE] ?? 0);
        if ($returnCode === 3) {
            // Still processing — no mutation, ZaloPay retries the callback.
            return self::OUTCOME_RETRYABLE_FAILURE;
        }
        if ($returnCode !== AbstractResponseValidator::RETURN_CODE_ACCEPT) {
            // Authoritative non-paid (return_code 2 or unknown): record it
            // where the fresh state permits — never regress money-real state.
            return $this->recordAuthoritativeFailureAndAcknowledge($appTransId, $returnCode);
        }

        $queryAmount = (int)($query[AbstractResponseValidator::TOTAL_AMOUNT] ?? 0);
        $queryZpTransId = $this->parseQueryZpTransId($query);
        $zpTransId = $queryZpTransId ?? $callbackZpTransId ?? '';

        if ($queryAmount === 0) {
            // v2/query says paid but provides no amount (documented: the
            // amount is only available when the payment is successful) —
            // WITHOUT a verified exact amount there is no automatic order.
            return $this->recordMismatchAndAcknowledge($appTransId, 0, $zpTransId, 'IPN-query');
        }
        $attempt = $this->repository->getByAppTransId($appTransId);
        $snapshot = $attempt !== null ? (int)$attempt->getAmount() : 0;
        if ($queryAmount !== $snapshot) {
            return $this->recordMismatchAndAcknowledge($appTransId, $queryAmount, $zpTransId, 'IPN-query');
        }

        // Money verified EXACT. Now the provider transaction identity must
        // be PROVEN (round 4, Blocker 1b/1c) — never finalize without it.
        if ($callbackZpTransId !== null && $queryZpTransId !== null
            && $callbackZpTransId !== $queryZpTransId
        ) {
            // Blocker 1c: two authoritative proofs claim DIFFERENT provider
            // transactions for the same app_trans_id. Quarantine with BOTH
            // identities preserved — never silently prefer either.
            $this->logger->critical(
                'ZaloPay IPN: callback zp_trans_id conflicts with the authoritative v2/query result; '
                . 'quarantined for reconciliation, no order placed.',
                [
                    'app_trans_id' => $appTransId,
                    'callback_zp_trans_id' => $callbackZpTransId,
                    'query_zp_trans_id' => $queryZpTransId,
                ]
            );
            $this->lifecycle->recordProviderIdentityConflict(
                $appTransId,
                $callbackZpTransId,
                $queryZpTransId,
                'IPN'
            );

            return self::OUTCOME_ACK_RECONCILIATION;
        }

        $providerTransactionId = $queryZpTransId ?? $callbackZpTransId;
        if ($providerTransactionId === null) {
            // Blocker 1b: verified money, NO provable positive zp_trans_id
            // from either authoritative proof — money-real quarantine, the
            // automatic order is structurally refused.
            $this->logger->critical(
                'ZaloPay IPN: verified payment without a positive zp_trans_id from callback or v2/query; '
                . 'quarantined for reconciliation, no order placed.',
                ['app_trans_id' => $appTransId]
            );
            $this->lifecycle->recordProviderIdentityUnavailable($appTransId, 'IPN-query');

            return self::OUTCOME_ACK_RECONCILIATION;
        }

        return $this->applyVerifiedPaidAndFinalize($appTransId, $providerTransactionId);
    }

    /**
     * STRICT parse of the signed callback `amount` (round 4). Integer
     * grammar only — a present non-integer value inside MAC-valid data is a
     * provider anomaly and must never be cast (blind casting maps "abc" to
     * 0 and sneaks it into the missing/zero query-fallback branch).
     *
     * @param array $transData
     * @return int|null|false The integer amount; NULL when the field is
     *         absent (query fallback allowed); FALSE when present but
     *         malformed (caller answers "Invalid", zero mutation).
     */
    private function parseCallbackAmount(array $transData)
    {
        if (!array_key_exists(AbstractResponseValidator::TOTAL_AMOUNT, $transData)) {
            return null;
        }
        $raw = $transData[AbstractResponseValidator::TOTAL_AMOUNT];
        if (is_bool($raw) || is_array($raw) || !is_numeric($raw)
            || preg_match('/^-?\d+$/', trim((string)$raw)) !== 1
        ) {
            return self::FIELD_MALFORMED;
        }

        return (int)$raw;
    }

    /**
     * STRICT parse of the signed callback `zp_trans_id` (round 4): only a
     * POSITIVE integer is a usable provider identity; zero/negative are
     * treated as absent (the authoritative query must prove the identity);
     * a present non-integer value is malformed.
     *
     * @param array $transData
     * @return string|null|false The canonical decimal id; NULL when absent
     *         or zero (query fallback required); FALSE when malformed.
     */
    private function parseCallbackZpTransId(array $transData)
    {
        if (!array_key_exists(AbstractResponseValidator::ZP_TRANS_ID, $transData)) {
            return null;
        }
        $raw = $transData[AbstractResponseValidator::ZP_TRANS_ID];
        if (is_bool($raw) || is_array($raw) || !is_numeric($raw)
            || preg_match('/^-?\d+$/', trim((string)$raw)) !== 1
        ) {
            return self::FIELD_MALFORMED;
        }
        $int = (int)$raw;

        return $int > 0 ? (string)$int : null;
    }

    /**
     * Parse the v2/query zp_trans_id (documented int64, "initiate when
     * users confirms payment at Zalopay site"): only a positive integer
     * proves the identity. This is OUR server-to-server response (key1-
     * signed request over TLS), so anything unusable degrades to null —
     * the safe direction (never auto-finalizes without identity).
     *
     * @param array $query
     * @return string|null
     */
    private function parseQueryZpTransId(array $query): ?string
    {
        $raw = $query[AbstractResponseValidator::ZP_TRANS_ID] ?? null;
        if ($raw === null || is_bool($raw) || is_array($raw) || !is_numeric($raw)) {
            return null;
        }
        $int = (int)$raw;

        return $int > 0 ? (string)$int : null;
    }

    /**
     * Whether the signed callback app_id matches the configured application
     * (strict integer comparison; absent/malformed never matches).
     *
     * @param array $transData
     * @param string $configuredAppId
     * @return bool
     */
    private function callbackAppIdMatches(array $transData, string $configuredAppId): bool
    {
        $callbackAppId = $transData[AbstractDataBuilder::APP_ID] ?? null;
        if (!is_scalar($callbackAppId) || !is_numeric((string)$callbackAppId)
            || preg_match('/^-?\d+$/', trim((string)$callbackAppId)) !== 1
        ) {
            return false;
        }

        return (int)$callbackAppId === (int)$configuredAppId;
    }

    /**
     * Deterministic amount mismatch: quarantine (structured
     * requires_reconciliation + evidence, money-real kept) and ACKNOWLEDGE
     * so ZaloPay stops retrying a callback that can never succeed.
     *
     * @param string $appTransId
     * @param int $paidAmount
     * @param string $zpTransId
     * @param string $source
     * @return string
     */
    private function recordMismatchAndAcknowledge(
        string $appTransId,
        int $paidAmount,
        string $zpTransId,
        string $source
    ): string {
        $this->logger->critical(
            'ZaloPay IPN amount mismatch: quarantined for reconciliation, no order placed.',
            [
                'app_trans_id' => $appTransId,
                'paid_amount' => $paidAmount,
                'source' => $source,
            ]
        );
        $this->lifecycle->recordAmountMismatch($appTransId, $paidAmount, $source, $zpTransId !== '' ? $zpTransId : null);

        return self::OUTCOME_ACK_RECONCILIATION;
    }

    /**
     * Authoritative failure (callback claimed paid, the query disagrees):
     * transition where the fresh state permits, keep money-real conflicts,
     * acknowledge — retries cannot change an authoritative result.
     *
     * @param string $appTransId
     * @param int $returnCode
     * @return string
     */
    private function recordAuthoritativeFailureAndAcknowledge(string $appTransId, int $returnCode): string
    {
        $fresh = $this->lifecycle->recordVerifiedFailure(
            $appTransId,
            sprintf('v2/query return_code %d.', $returnCode),
            'failed'
        );
        $status = $fresh->getPaymentStatus();
        if ($status === PaymentAttemptInterface::STATUS_FINALIZED) {
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
        if ($status === PaymentAttemptInterface::STATUS_PAID) {
            $this->logger->critical(
                'ZaloPay IPN: authoritative failure conflicts with recorded PAID; kept money-real.',
                ['app_trans_id' => $appTransId, 'return_code' => $returnCode]
            );
        }

        return self::OUTCOME_ACK_RECONCILIATION;
    }

    /**
     * Amount verified EXACT: canonical lifecycle PAID transition on the
     * LOCKED fresh row, then the single finalization boundary.
     *
     * @param string $appTransId
     * @param string $zpTransId
     * @return string
     */
    private function applyVerifiedPaidAndFinalize(string $appTransId, string $zpTransId): string
    {
        // Idempotent for PAID/FINALIZED, evidence-only for terminal unpaid
        // states, quarantining for a conflicting zp_trans_id.
        $fresh = $this->lifecycle->recordVerifiedPaid($appTransId, $zpTransId !== '' ? $zpTransId : null);

        if ($fresh->getRequiresReconciliation()) {
            // Conflicting provider identity or stale mismatch evidence —
            // never auto-finalize (corrective round 3, Blocker 5).
            $this->logger->critical(
                'ZaloPay IPN: attempt quarantined; no automatic order.',
                [
                    'app_trans_id' => $appTransId,
                    'reconciliation_code' => (string)$fresh->getReconciliationCode(),
                ]
            );

            return self::OUTCOME_ACK_RECONCILIATION;
        }

        $status = $fresh->getPaymentStatus();
        if ($status !== PaymentAttemptInterface::STATUS_PAID
            && $status !== PaymentAttemptInterface::STATUS_FINALIZED
        ) {
            // e.g. STALE/EXPIRED/FAILED attempt whose paid callback arrived
            // late: evidence + provider identity persisted, no order,
            // acknowledge (retrying cannot fix it).
            return self::OUTCOME_ACK_RECONCILIATION;
        }

        // Canonical finalization NOW — server-to-server, no browser needed.
        // PAID (fresh or from an earlier failed finalize) is retryable;
        // FINALIZED recovers the bound order (duplicate IPN idempotent).
        try {
            $this->orderFinalizer->finalizeOrRecover($fresh, $zpTransId);
        } catch (ContractMismatchException $exception) {
            // Deterministic: the money is real but the current quote/order
            // contract cannot be honoured. The lifecycle quarantined the
            // attempt with the evidence. Acknowledge to stop provider
            // retries of an unfixable callback.
            return self::OUTCOME_ACK_RECONCILIATION;
        } catch (\Exception $exception) {
            // Transient technical failure — the attempt remains PAID (the
            // finalizer's transaction rolled back). Ask ZaloPay to retry.
            $this->logger->critical(
                sprintf(
                    'ZaloPay IPN finalization failed (retryable), attempt #%d: %s',
                    (int)$fresh->getEntityId(),
                    $exception->getMessage()
                )
            );

            return self::OUTCOME_RETRYABLE_FAILURE;
        }

        return self::OUTCOME_SUCCESS;
    }

    /**
     * Run the authoritative v2/query for the attempt (OUTSIDE any DB
     * transaction — verification never holds row locks).
     *
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
                'ZaloPay v2/query failed for IPN amount verification: ' . $e->getMessage(),
                ['app_trans_id' => $appTransId]
            );
            throw new LocalizedException(__('ZaloPay payment could not be verified right now.'));
        }

        return $result->get();
    }
}
