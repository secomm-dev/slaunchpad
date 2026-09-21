<?php
/**
 * Unit tests for the MoMo refund result classifier (MOMO-02).
 *
 * Covers the response-integrity contract: SUCCESS only on intact echoes +
 * resultCode 0 with a valid positive transId; strict integer grammar for
 * provider amounts; non-final codes (7002, 7000, 21, …) → UNKNOWN;
 * provider-confirmed final refusal → FAILED; echo mismatch / malformed →
 * UNKNOWN (never FAILED); query-based resolve parsing binds the response to
 * the EXACT query sent (top-level requestId/orderId/partnerCode echoes)
 * and requires an EXACT orderId match on refundTrans entries (no
 * single-entry fallback).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Service;

use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Api\Data\RefundRequestInterface;
use Secomm\MoMo\Service\RefundResultClassifier;

class RefundResultClassifierTest extends TestCase
{
    private const REFUND_ORDER_ID = 'MOMO2609180000-SLP-1-RF1111';
    private const REQUEST_ID = 'MOMO2609180000-SLP-1-RQ2222';
    private const QUERY_REQUEST_ID = 'MOMO2609180000-SLP-1-RF1111-QQaaaa';
    private const AMOUNT = 150000;

    /**
     * @return RefundResultClassifier
     */
    private function classifier(): RefundResultClassifier
    {
        return new RefundResultClassifier();
    }

    /**
     * A minimal valid refund response builder (provider echo shape).
     *
     * @param array $overrides
     * @return array
     */
    private function response(array $overrides = []): array
    {
        return array_merge([
            'partnerCode' => 'SECOMM',
            'orderId' => self::REFUND_ORDER_ID,
            'requestId' => self::REQUEST_ID,
            'amount' => self::AMOUNT,
            'transId' => 2820086739,
            'resultCode' => 0,
            'message' => ' Successful',
        ], $overrides);
    }

    /**
     * Expected echoes builder (identity of the request just sent).
     *
     * @param array $overrides
     * @return array
     */
    private function expected(array $overrides = []): array
    {
        return array_merge([
            'requestId' => self::REQUEST_ID,
            'refund_order_id' => self::REFUND_ORDER_ID,
            'amount' => self::AMOUNT,
            'partner_code' => 'SECOMM',
        ], $overrides);
    }

    /**
     * @return void
     */
    public function testSuccessWhenEchoesIntactAndResultCodeZero(): void
    {
        $classification = $this->classifier()->classify($this->expected(), $this->response());
        $this->assertTrue($classification->isSuccess());
        $this->assertSame(RefundRequestInterface::REASON_PROVIDER_CONFIRMED, $classification->reason);
        $this->assertSame('2820086739', $classification->providerTransactionId);
    }

    /**
     * @return void
     */
    public function testProcessingResultCodeSevenZeroZeroTwoIsUnknown(): void
    {
        $classification = $this->classifier()->classify(
            $this->expected(),
            $this->response(['resultCode' => 7002, 'transId' => 0])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_PROVIDER_PROCESSING, $classification->reason);
    }

    /**
     * A provider-confirmed FINAL failure code resolves FAILED (releases the
     * open slot). 99 is outside the non-final set.
     *
     * @return void
     */
    public function testFinalFailureCodeIsFailed(): void
    {
        $classification = $this->classifier()->classify(
            $this->expected(),
            $this->response(['resultCode' => 99, 'message' => ' Refused'])
        );
        $this->assertSame(RefundRequestInterface::STATUS_FAILED, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_PROVIDER_REFUSED, $classification->reason);
    }

    /**
     * Non-final codes (Final Status = No — money may still move) must never
     * resolve FAILED; they stay UNKNOWN with reason provider_processing.
     * Regression: previously only 7002 was special-cased; the correction
     * rounds add the full provider contract list (10/11/12/13, 20/21/22,
     * 40/41/42/43/45/47, 1000, 7000, 9000).
     *
     * @return void
     */
    public function testNonFinalCodesAreUnknownProcessing(): void
    {
        $nonFinalCodes = [
            '10', '11', '12', '13', '20', '21', '22',
            '40', '41', '42', '43', '45', '47', '1000', '7000', '9000',
        ];
        foreach ($nonFinalCodes as $code) {
            $classification = $this->classifier()->classify(
                $this->expected(),
                $this->response(['resultCode' => (int)$code, 'transId' => 0])
            );
            $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
            $this->assertSame(RefundRequestInterface::REASON_PROVIDER_PROCESSING, $classification->reason);
        }
    }

    /**
     * @return void
     */
    public function testRequestIdEchoMismatchIsUnknown(): void
    {
        $classification = $this->classifier()->classify(
            $this->expected(['requestId' => 'MOMO-OTHER-RQ']),
            $this->response()
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_ECHO_MISMATCH, $classification->reason);
    }

    /**
     * @return void
     */
    public function testOrderIdEchoMismatchIsUnknown(): void
    {
        $classification = $this->classifier()->classify(
            $this->expected(['refund_order_id' => 'MOMO-OTHER-RF']),
            $this->response()
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_ECHO_MISMATCH, $classification->reason);
    }

    /**
     * @return void
     */
    public function testAmountEchoMismatchIsUnknown(): void
    {
        $classification = $this->classifier()->classify(
            $this->expected(['amount' => 999]),
            $this->response()
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_ECHO_MISMATCH, $classification->reason);
    }

    /**
     * A DIFFERENT partnerCode cannot be trusted even with resultCode 0.
     *
     * @return void
     */
    public function testPartnerCodeConflictIsUnknown(): void
    {
        $classification = $this->classifier()->classify(
            $this->expected(),
            $this->response(['partnerCode' => 'NOT-SECOMM'])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_ECHO_MISMATCH, $classification->reason);
    }

    /**
     * Missing partnerCode alone must not fail the classification.
     *
     * @return void
     */
    public function testMissingPartnerCodeTolerated(): void
    {
        $classification = $this->classifier()->classify(
            $this->expected(),
            $this->response(['partnerCode' => null])
        );
        $this->assertTrue($classification->isSuccess());
    }

    /**
     * @return void
     */
    public function testEmptyResponseIsUnknownMalformed(): void
    {
        $classification = $this->classifier()->classify($this->expected(), []);
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_MALFORMED_RESPONSE, $classification->reason);
    }

    /**
     * @return void
     */
    public function testMissingResultCodeIsUnknownMalformed(): void
    {
        $classification = $this->classifier()->classify(
            $this->expected(),
            $this->response(['resultCode' => null])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_MALFORMED_RESPONSE, $classification->reason);
    }

    /**
     * A malformed amount ("150000abc") must never (int)-cast into a
     * plausible number and pass the echo check — strict grammar → UNKNOWN.
     *
     * @return void
     */
    public function testMalformedAmountNeverPassesEchoCheck(): void
    {
        $classification = $this->classifier()->classify(
            $this->expected(),
            $this->response(['amount' => '150000abc'])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_ECHO_MISMATCH, $classification->reason);
    }

    /**
     * A JSON number delivered as an all-digit STRING is well-formed and
     * accepted by the strict grammar.
     *
     * @return void
     */
    public function testDigitStringAmountIsAccepted(): void
    {
        $classification = $this->classifier()->classify(
            $this->expected(),
            $this->response(['amount' => '150000'])
        );
        $this->assertTrue($classification->isSuccess());
    }

    /**
     * A float amount (e.g. JSON 150000.0) is not a well-formed integer in
     * the strict grammar — conservative UNKNOWN, never trusted.
     *
     * @return void
     */
    public function testFloatAmountIsNeverTrusted(): void
    {
        $classification = $this->classifier()->classify(
            $this->expected(),
            $this->response(['amount' => 150000.0])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_ECHO_MISMATCH, $classification->reason);
    }

    /**
     * resultCode 0 without a transId lacks the provider evidence needed to
     * trust SUCCESS — UNKNOWN (malformed), never SUCCESS.
     *
     * @return void
     */
    public function testSuccessWithoutTransIdIsUnknown(): void
    {
        $classification = $this->classifier()->classify(
            $this->expected(),
            $this->response(['transId' => null])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_MALFORMED_RESPONSE, $classification->reason);
    }

    /**
     * resultCode 0 with transId 0 is not a valid positive transaction id —
     * UNKNOWN (malformed), never SUCCESS.
     *
     * @return void
     */
    public function testSuccessWithZeroTransIdIsUnknown(): void
    {
        $classification = $this->classifier()->classify(
            $this->expected(),
            $this->response(['transId' => 0])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_MALFORMED_RESPONSE, $classification->reason);
    }

    // ---- classifyQuery: operator resolve path ----

    /**
     * Expected identity for a resolve query: the stored refund row plus the
     * FRESH query requestId that was just signed and sent.
     *
     * @param array $overrides
     * @return array
     */
    private function queryExpected(array $overrides = []): array
    {
        return array_merge([
            'refund_order_id' => self::REFUND_ORDER_ID,
            'amount' => self::AMOUNT,
            'query_request_id' => self::QUERY_REQUEST_ID,
            'partner_code' => 'SECOMM',
        ], $overrides);
    }

    /**
     * A minimal valid refund/query response (top-level query identity echo
     * + refundTrans evidence entries).
     *
     * @return array
     */
    private function queryResponse(array $entries = [], array $overrides = []): array
    {
        return array_merge([
            'partnerCode' => 'SECOMM',
            'orderId' => self::REFUND_ORDER_ID,
            'requestId' => self::QUERY_REQUEST_ID,
            'resultCode' => 0,
            'message' => ' Success',
            'refundTrans' => $entries,
        ], $overrides);
    }

    /**
     * @return array
     */
    private function queryEntry(array $overrides = []): array
    {
        return array_merge([
            'orderId' => self::REFUND_ORDER_ID,
            'amount' => self::AMOUNT,
            'resultCode' => 0,
            'transId' => 2820086740,
            'createdTime' => '2026-09-18 10:00:00',
        ], $overrides);
    }

    /**
     * A response that does NOT echo the exact fresh query requestId this
     * invocation just sent is not evidence about this row — UNKNOWN, never
     * terminal (a stale response from an earlier query must never resolve).
     *
     * @return void
     */
    public function testQueryStaleRequestIdEchoIsUnknown(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->queryExpected(),
            $this->queryResponse([$this->queryEntry()], ['requestId' => 'MOMO-STALE-QQ9999'])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_ECHO_MISMATCH, $classification->reason);
    }

    /**
     * A missing top-level requestId echo is missing material identity.
     *
     * @return void
     */
    public function testQueryMissingRequestIdEchoIsUnknown(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->queryExpected(),
            $this->queryResponse([$this->queryEntry()], ['requestId' => null])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_ECHO_MISMATCH, $classification->reason);
    }

    /**
     * A top-level orderId that does not name THIS refund's orderId means the
     * answer describes something else — UNKNOWN, even with matching entries.
     *
     * @return void
     */
    public function testQueryTopLevelOrderIdMismatchIsUnknown(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->queryExpected(),
            $this->queryResponse([$this->queryEntry()], ['orderId' => 'OTHER-RF'])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_ECHO_MISMATCH, $classification->reason);
    }

    /**
     * A DIFFERENT partnerCode in the query response cannot be trusted.
     *
     * @return void
     */
    public function testQueryPartnerCodeConflictIsUnknown(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->queryExpected(),
            $this->queryResponse([$this->queryEntry()], ['partnerCode' => 'NOT-SECOMM'])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_ECHO_MISMATCH, $classification->reason);
    }

    /**
     * Missing partnerCode in the query response is tolerated (same rule as
     * the direct refund path: only a CONFLICT misdirects evidence).
     *
     * @return void
     */
    public function testQueryMissingPartnerCodeTolerated(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->queryExpected(),
            $this->queryResponse([$this->queryEntry()], ['partnerCode' => null])
        );
        $this->assertTrue($classification->isSuccess());
    }

    /**
     * A resolved (resultCode 0) entry with a transId confirms the refund.
     *
     * @return void
     */
    public function testQueryResolvesSuccessWithTransId(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->queryExpected(),
            $this->queryResponse([$this->queryEntry()])
        );
        $this->assertTrue($classification->isSuccess());
        $this->assertSame('2820086740', $classification->providerTransactionId);
    }

    /**
     * Entries nested under items[] are found too.
     *
     * @return void
     */
    public function testQueryReadsEntriesNestedInItems(): void
    {
        $response = $this->queryResponse([], ['items' => [['refundTrans' => [$this->queryEntry()]]]]);
        $classification = $this->classifier()->classifyQuery($this->queryExpected(), $response);
        $this->assertTrue($classification->isSuccess());
    }

    /**
     * NO single-entry fallback: an entry whose orderId differs from the
     * stored refund_order_id is never accepted as THE refund, even when it
     * is the only entry — an unrelated refund with a coincidentally equal
     * amount must not resolve this row.
     *
     * @return void
     */
    public function testQuerySingleNonMatchingEntryIsNeverAccepted(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->queryExpected(),
            $this->queryResponse([$this->queryEntry(['orderId' => 'OTHER-RF'])])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_ECHO_MISMATCH, $classification->reason);
    }

    /**
     * Multiple non-matching entries = ambiguous evidence; never guess.
     *
     * @return void
     */
    public function testQueryAmbiguousEntriesStayUnknown(): void
    {
        $entries = [
            $this->queryEntry(['orderId' => 'OTHER-RF-1', 'resultCode' => 21]),
            $this->queryEntry(['orderId' => 'OTHER-RF-2', 'resultCode' => 21]),
        ];
        $classification = $this->classifier()->classifyQuery($this->queryExpected(), $this->queryResponse($entries));
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_ECHO_MISMATCH, $classification->reason);
    }

    /**
     * Top-level query resultCode != 0 is a rejected query, not a verdict.
     *
     * @return void
     */
    public function testQueryRejectionIsUnknown(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->queryExpected(),
            $this->queryResponse([], ['resultCode' => 19, 'message' => ' Data not found'])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_QUERY_REJECTED, $classification->reason);
    }

    /**
     * An entry still processing (7002) stays UNKNOWN — money may still move.
     *
     * @return void
     */
    public function testQueryProcessingEntryStaysUnknown(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->queryExpected(),
            $this->queryResponse([$this->queryEntry(['resultCode' => 7002])])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_PROVIDER_PROCESSING, $classification->reason);
    }

    /**
     * An entry refused with a provider-confirmed FINAL failure code
     * resolves the row as FAILED (releases the open slot).
     *
     * @return void
     */
    public function testQueryRefusedEntryResolvesFailed(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->queryExpected(),
            $this->queryResponse([$this->queryEntry(['resultCode' => 99])])
        );
        $this->assertSame(RefundRequestInterface::STATUS_FAILED, $classification->status);
    }

    /**
     * A non-final entry code (Final Status = No) keeps the row UNKNOWN
     * (provider_processing) — money may still move, the slot stays open.
     * Regression for the correction round: 21 was previously FAILED.
     *
     * @return void
     */
    public function testQueryNonFinalEntryCodeStaysUnknown(): void
    {
        foreach (['21', '1000', '7000', '9000'] as $code) {
            $classification = $this->classifier()->classifyQuery(
                $this->queryExpected(),
                $this->queryResponse([$this->queryEntry(['resultCode' => (int)$code])])
            );
            $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
            $this->assertSame(RefundRequestInterface::REASON_PROVIDER_PROCESSING, $classification->reason);
        }
    }

    /**
     * A SUCCESS entry with transId 0 lacks valid positive transaction-id
     * evidence — UNKNOWN (malformed), never SUCCESS.
     *
     * @return void
     */
    public function testQuerySuccessWithZeroTransIdIsUnknown(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->queryExpected(),
            $this->queryResponse([$this->queryEntry(['transId' => 0])])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_MALFORMED_RESPONSE, $classification->reason);
    }

    /**
     * A malformed entry amount ("150000abc") must never (int)-cast into a
     * plausible number — strict grammar, echo mismatch → UNKNOWN.
     *
     * @return void
     */
    public function testQueryMalformedAmountIsUnknown(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->queryExpected(),
            $this->queryResponse([$this->queryEntry(['amount' => '150000abc'])])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_ECHO_MISMATCH, $classification->reason);
    }

    /**
     * A SUCCESS entry without transId lacks the evidence needed to trust it.
     *
     * @return void
     */
    public function testQuerySuccessWithoutTransIdIsUnknown(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->queryExpected(),
            $this->queryResponse([$this->queryEntry(['transId' => null])])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_MALFORMED_RESPONSE, $classification->reason);
    }

    /**
     * An entry whose amount differs from the stored row must not be trusted.
     *
     * @return void
     */
    public function testQueryAmountMismatchIsUnknown(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->queryExpected(),
            $this->queryResponse([$this->queryEntry(['amount' => 999])])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_ECHO_MISMATCH, $classification->reason);
    }

    /**
     * An empty refundTrans list cannot resolve anything.
     *
     * @return void
     */
    public function testQueryWithoutEntriesIsUnknown(): void
    {
        $classification = $this->classifier()->classifyQuery($this->queryExpected(), $this->queryResponse([]));
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_ECHO_MISMATCH, $classification->reason);
    }
}
