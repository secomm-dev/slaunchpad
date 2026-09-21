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
     * Provider resultCodes meaning "still processing" — money may move, so
     * they must never be classified FAILED (a wrong FAILED would release the
     * open slot and invite a double refund).
     */
    public const PROCESSING_RESULT_CODES = ['7002'];

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
        $echo = [
            'requestId' => (string)($response['requestId'] ?? ''),
            'orderId' => (string)($response['orderId'] ?? ''),
            'amount' => (int)($response['amount'] ?? -1),
        ];

        if ($echo['requestId'] !== (string)$expected['requestId']
            || $echo['orderId'] !== (string)$expected['refund_order_id']
            || $echo['amount'] !== (int)$expected['amount']
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
            return new RefundClassification(
                RefundRequestInterface::STATUS_SUCCESS,
                RefundRequestInterface::REASON_PROVIDER_CONFIRMED,
                $responseCode,
                $this->readMessage($response),
                (string)($response['transId'] ?? '')
            );
        }

        if (in_array($responseCode, self::PROCESSING_RESULT_CODES, true)) {
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
     * Classify a refund/query response (operator resolve path).
     *
     * Defensive parsing: the evidence entry is matched by the refund's own
     * orderId; ambiguity or absence keeps the row UNKNOWN — a resolve call
     * must never GUESS a terminal verdict.
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
        if ($matches === [] && count($entries) === 1) {
            $matches = $entries;
        }
        if (count($matches) !== 1) {
            return new RefundClassification(
                RefundRequestInterface::STATUS_UNKNOWN,
                RefundRequestInterface::REASON_ECHO_MISMATCH,
                (string)$queryCode,
                $this->readMessage($response)
            );
        }

        $entry = $matches[0];
        $entryAmount = (int)($entry['amount'] ?? -1);
        $entryCode = $entry['resultCode'] ?? null;
        if ($entryAmount !== (int)$expected['amount']) {
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
            $transId = (string)($entry['transId'] ?? '');
            if ($transId === '') {
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
                $transId
            );
        }

        if (in_array($entryCode, self::PROCESSING_RESULT_CODES, true)) {
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
