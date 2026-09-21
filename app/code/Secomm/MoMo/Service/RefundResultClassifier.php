<?php
/**
 * Classifies a MoMo refund response against the exact request just sent.
 *
 * Provider contract (developers.momo.vn, /v2/gateway/api/refund, verified
 * 2026-09-18): the refund response carries partnerCode, orderId, requestId,
 * amount, transId, resultCode, message — and NO signature (same as the
 * query response; the merchant-initiated HTTPS call is the server-side
 * verification). Trusting SUCCESS therefore requires the echoes of the
 * EXACT request this command just sent plus resultCode == 0 — an HTTP 2xx
 * alone never proves a refund.
 *
 * resultCode 7002 ("Transaction is being processed by the provider of the
 * payment instrument selected") is NOT a refusal: money may still move, so
 * it classifies as UNKNOWN, never FAILED.
 *
 * The provider result-code contract (developers.momo.vn, correction round
 * MOMO-02, 2026-09-21) marks the codes 10/11/12/13, 20/21/22, 40/41/42/43/
 * 45/47, 7000, 7002 and 9000 with Final Status = No: money may still move,
 * so they are all UNKNOWN (open slot kept) — only a provider-confirmed
 * FINAL failure code may become FAILED and release the open slot.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Service;

use Secomm\MoMo\Api\Data\RefundRequestInterface;

class RefundResultClassifier
{
    /**
     * Provider resultCodes with Final Status = No (developers.momo.vn
     * result-code contract, per the MOMO-02 correction review). Money may
     * still move for these codes, so they must never be classified FAILED
     * (a wrong FAILED would release the open slot and invite a double
     * refund) — they are UNKNOWN (provider_processing), keeping the row
     * open until the provider settles a final answer.
     */
    public const NON_FINAL_RESULT_CODES = [
        '10', '11', '12', '13',
        '20', '21', '22',
        '40', '41', '42', '43', '45', '47',
        '7000', '7002', '9000',
    ];

    /**
     * Classify a refund response against the request echoes.
     *
     * @param array $expected Exact request identity: requestId, refund_order_id, amount, partner_code.
     * @param array $response Raw decoded provider response (empty on malformed body).
     * @return RefundClassification
     */
    public function classify(array $expected, array $response): RefundClassification
    {
        $responseCode = $response['resultCode'] ?? null;
        if ($response === [] || $responseCode === null || !is_scalar($responseCode)) {
            return new RefundClassification(
                RefundRequestInterface::STATUS_UNKNOWN,
                RefundRequestInterface::REASON_MALFORMED_RESPONSE
            );
        }

        $responseCode = (string)$responseCode;
        $responseAmount = $this->strictInt($response['amount'] ?? null);
        if ((string)($response['requestId'] ?? '') !== (string)$expected['requestId']
            || (string)($response['orderId'] ?? '') !== (string)$expected['refund_order_id']
            || $responseAmount === null
            || $responseAmount !== (int)$expected['amount']
        ) {
            return new RefundClassification(
                RefundRequestInterface::STATUS_UNKNOWN,
                RefundRequestInterface::REASON_ECHO_MISMATCH,
                $responseCode,
                $this->readMessage($response)
            );
        }

        // partnerCode: strict on conflict, tolerant on absence (the provider
        // contract lists it, but a missing field alone cannot redirect money
        // — only a DIFFERENT partnerCode could, and that must not be trusted).
        $partnerCode = (string)($response['partnerCode'] ?? '');
        $expectedPartner = (string)($expected['partner_code'] ?? '');
        if ($partnerCode !== '' && $expectedPartner !== '' && $partnerCode !== $expectedPartner) {
            return new RefundClassification(
                RefundRequestInterface::STATUS_UNKNOWN,
                RefundRequestInterface::REASON_ECHO_MISMATCH,
                $responseCode,
                $this->readMessage($response)
            );
        }

        if ($responseCode === '0') {
            // SUCCESS needs the provider's own refund transId as evidence;
            // a resultCode 0 without a valid positive transId is malformed,
            // not trusted (AC5 — same conservative rule as the query path).
            $transId = $response['transId'] ?? null;
            if (!$this->isValidTransId($transId)) {
                return new RefundClassification(
                    RefundRequestInterface::STATUS_UNKNOWN,
                    RefundRequestInterface::REASON_MALFORMED_RESPONSE,
                    $responseCode,
                    $this->readMessage($response)
                );
            }

            return new RefundClassification(
                RefundRequestInterface::STATUS_SUCCESS,
                RefundRequestInterface::REASON_PROVIDER_CONFIRMED,
                $responseCode,
                $this->readMessage($response),
                (string)$transId
            );
        }

        if (in_array($responseCode, self::NON_FINAL_RESULT_CODES, true)) {
            return new RefundClassification(
                RefundRequestInterface::STATUS_UNKNOWN,
                RefundRequestInterface::REASON_PROVIDER_PROCESSING,
                $responseCode,
                $this->readMessage($response)
            );
        }

        return new RefundClassification(
            RefundRequestInterface::STATUS_FAILED,
            RefundRequestInterface::REASON_PROVIDER_REFUSED,
            $responseCode,
            $this->readMessage($response)
        );
    }

    /**
     * Read the provider message (truncated, raw provider text — no secrets).
     *
     * @param array $response
     * @return string|null
     */
    private function readMessage(array $response): ?string
    {
        $message = $response['message'] ?? null;
        if ($message === null || !is_scalar($message)) {
            return null;
        }

        return mb_substr((string)$message, 0, 255);
    }

    /**
     * Strict integer grammar for provider-sourced numerics: only a JSON
     * integer or an all-digit string parses. A malformed value like
     * "150000abc" must never cast into a plausible number (it would pass
     * the amount echo check and trust a wrong refund).
     *
     * @param mixed $value
     * @return int|null null when the value is not a well-formed integer.
     */
    private function strictInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ctype_digit($value)) {
            return (int)$value;
        }

        return null;
    }

    /**
     * A provider transId is trusted as SUCCESS evidence only as a valid
     * positive integer (int or digit string, > 0) — the same conservative
     * rule on the direct refund and the query resolve paths.
     *
     * @param mixed $value
     * @return bool
     */
    private function isValidTransId(mixed $value): bool
    {
        $transId = $this->strictInt($value);

        return $transId !== null && $transId > 0;
    }

    /**
     * Classify a refund/query response (operator resolve path).
     *
     * Defensive parsing: the evidence entry must match the refund's own
     * orderId EXACTLY — no single-entry fallback, because a query on a
     * shared purchase order can legitimately return a sibling refund with
     * a coincidentally equal amount. Ambiguity or mismatch keeps the row
     * UNKNOWN — a resolve call must never GUESS a terminal verdict.
     *
     * @param array $expected Identity of the stored refund row:
     *        refund_order_id, amount.
     * @param array $response Raw decoded refund/query response.
     * @return RefundClassification
     */
    public function classifyQuery(array $expected, array $response): RefundClassification
    {
        $queryCode = $response['resultCode'] ?? null;
        if ($response === [] || $queryCode === null || !is_scalar($queryCode)) {
            return new RefundClassification(
                RefundRequestInterface::STATUS_UNKNOWN,
                RefundRequestInterface::REASON_MALFORMED_RESPONSE
            );
        }

        if ((string)$queryCode !== '0') {
            return new RefundClassification(
                RefundRequestInterface::STATUS_UNKNOWN,
                RefundRequestInterface::REASON_QUERY_REJECTED,
                (string)$queryCode,
                $this->readMessage($response)
            );
        }

        // Normalize once: providers may answer with a JSON number — every
        // verdict below carries the code as a string.
        $queryCode = (string)$queryCode;

        $entries = $this->collectRefundEntries($response);
        $matches = array_values(array_filter(
            $entries,
            fn (array $entry): bool => (string)($entry['orderId'] ?? '') === (string)$expected['refund_order_id']
        ));
        if (count($matches) !== 1) {
            return new RefundClassification(
                RefundRequestInterface::STATUS_UNKNOWN,
                RefundRequestInterface::REASON_ECHO_MISMATCH,
                (string)$queryCode,
                $this->readMessage($response)
            );
        }

        $entry = $matches[0];
        $entryAmount = $this->strictInt($entry['amount'] ?? null);
        $entryCode = $entry['resultCode'] ?? null;
        if ($entryAmount === null || $entryAmount !== (int)$expected['amount']) {
            return new RefundClassification(
                RefundRequestInterface::STATUS_UNKNOWN,
                RefundRequestInterface::REASON_ECHO_MISMATCH,
                (string)$queryCode,
                $this->readMessage($response)
            );
        }

        if ($entryCode === null || !is_scalar($entryCode)) {
            return new RefundClassification(
                RefundRequestInterface::STATUS_UNKNOWN,
                RefundRequestInterface::REASON_MALFORMED_RESPONSE,
                (string)$queryCode,
                $this->readMessage($response)
            );
        }

        $entryCode = (string)$entryCode;
        if ($entryCode === '0') {
            $transId = $entry['transId'] ?? null;
            if (!$this->isValidTransId($transId)) {
                return new RefundClassification(
                    RefundRequestInterface::STATUS_UNKNOWN,
                    RefundRequestInterface::REASON_MALFORMED_RESPONSE,
                    (string)$queryCode,
                    $this->readMessage($response)
                );
            }

            return new RefundClassification(
                RefundRequestInterface::STATUS_SUCCESS,
                RefundRequestInterface::REASON_PROVIDER_CONFIRMED,
                $queryCode,
                $this->readMessage($response),
                (string)$transId
            );
        }

        if (in_array($entryCode, self::NON_FINAL_RESULT_CODES, true)) {
            return new RefundClassification(
                RefundRequestInterface::STATUS_UNKNOWN,
                RefundRequestInterface::REASON_PROVIDER_PROCESSING,
                $queryCode,
                $this->readMessage($response)
            );
        }

        return new RefundClassification(
            RefundRequestInterface::STATUS_FAILED,
            RefundRequestInterface::REASON_PROVIDER_REFUSED,
            $queryCode,
            $this->readMessage($response)
        );
    }

    /**
     * Collect refund transaction entries from the defensive response shape:
     * refundTrans[] at the top level and/or inside items[].
     *
     * @param array $response
     * @return array
     */
    private function collectRefundEntries(array $response): array
    {
        $entries = [];
        $direct = $response['refundTrans'] ?? null;
        if (is_array($direct)) {
            foreach ($direct as $entry) {
                if (is_array($entry)) {
                    $entries[] = $entry;
                }
            }
        }
        $items = $response['items'] ?? null;
        if (is_array($items)) {
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $nested = $item['refundTrans'] ?? null;
                if (is_array($nested)) {
                    foreach ($nested as $entry) {
                        if (is_array($entry)) {
                            $entries[] = $entry;
                        }
                    }
                }
            }
        }

        return $entries;
    }
}
