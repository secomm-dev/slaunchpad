<?php
/**
 * Unit tests for the MoMo refund result classifier (MOMO-02).
 *
 * Covers the response-integrity contract: SUCCESS only on intact echoes +
 * resultCode 0; 7002 processing → UNKNOWN; provider refusal → FAILED;
 * echo mismatch / malformed → UNKNOWN (never FAILED); query-based resolve
 * parsing stays conservative on ambiguity.
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
     * @return void
     */
    public function testOtherNonZeroResultCodeIsFailed(): void
    {
        $classification = $this->classifier()->classify(
            $this->expected(),
            $this->response(['resultCode' => 21, 'message' => ' Refused'])
        );
        $this->assertSame(RefundRequestInterface::STATUS_FAILED, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_PROVIDER_REFUSED, $classification->reason);
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

    // ---- classifyQuery: operator resolve path ----

    /**
     * @return array
     */
    private function queryResponse(array $entries = [], array $overrides = []): array
    {
        return array_merge([
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
     * A resolved (resultCode 0) entry with a transId confirms the refund.
     *
     * @return void
     */
    public function testQueryResolvesSuccessWithTransId(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->expected(),
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
        $classification = $this->classifier()->classifyQuery($this->expected(), $response);
        $this->assertTrue($classification->isSuccess());
    }

    /**
     * One entry whose orderId differs is still accepted as THE refund (the
     * query was sent with this refund's identity).
     *
     * @return void
     */
    public function testQueryFallsBackToSingleEntry(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->expected(),
            $this->queryResponse([$this->queryEntry(['orderId' => 'OTHER-RF'])])
        );
        $this->assertTrue($classification->isSuccess());
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
        $classification = $this->classifier()->classifyQuery($this->expected(), $this->queryResponse($entries));
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
            $this->expected(),
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
            $this->expected(),
            $this->queryResponse([$this->queryEntry(['resultCode' => 7002])])
        );
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_PROVIDER_PROCESSING, $classification->reason);
    }

    /**
     * An entry refused by the provider resolves the row as FAILED.
     *
     * @return void
     */
    public function testQueryRefusedEntryResolvesFailed(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->expected(),
            $this->queryResponse([$this->queryEntry(['resultCode' => 21])])
        );
        $this->assertSame(RefundRequestInterface::STATUS_FAILED, $classification->status);
    }

    /**
     * A SUCCESS entry without transId lacks the evidence needed to trust it.
     *
     * @return void
     */
    public function testQuerySuccessWithoutTransIdIsUnknown(): void
    {
        $classification = $this->classifier()->classifyQuery(
            $this->expected(),
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
            $this->expected(),
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
        $classification = $this->classifier()->classifyQuery($this->expected(), $this->queryResponse([]));
        $this->assertSame(RefundRequestInterface::STATUS_UNKNOWN, $classification->status);
        $this->assertSame(RefundRequestInterface::REASON_ECHO_MISMATCH, $classification->reason);
    }
}
