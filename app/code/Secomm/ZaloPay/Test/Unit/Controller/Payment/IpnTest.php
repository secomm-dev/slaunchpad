<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Controller\Payment;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Controller\Payment\Ipn;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Service\IpnProcessor;

/**
 * IPN controller = composition ONLY (corrective round 3, Blocker 3).
 *
 * The legacy `{errors, messages}` body + 404/500 HTTP codes were NOT the
 * ZaloPay provider protocol. The official callback contract
 * (https://docs.zalopay.vn/docs/specs/callback-api/ + knowledge base
 * "Callback") is: ALWAYS HTTP 200 with JSON `{return_code, return_message}`
 * — `1` = "Success", `2` = "Invalid", `0` = "callback again" (official
 * sample, transient). Proven here per documented outcome, plus:
 *  - POST-only (the provider contract is POST; HttpGetActionInterface was
 *    removed — reflection-proven);
 *  - session-free (case 28: the IPN is server-to-server, no checkout
 *    session can ever be touched);
 *  - an exception still answers the documented retryable body (never a 500).
 */
class IpnTest extends TestCase
{
    private const TRANS_DATA = ['app_trans_id' => '260826_1000_000000123', 'amount' => 100000];
    private const DATA_STRING = '{"app_id":2554,"app_trans_id":"260826_1000_000000123"}';
    private const MAC = 'a1b2c3';
    private const RAW_BODY = '{"data":"{\"app_id\":2554}","mac":"a1b2c3"}';

    /**
     * @var Http|MockObject
     */
    private $request;

    /**
     * @var Json|MockObject
     */
    private $resultJson;

    /**
     * @var IpnProcessor|MockObject
     */
    private $processor;

    /**
     * @var Ipn
     */
    private $controller;

    protected function setUp(): void
    {
        $this->request = $this->createMock(Http::class);
        $this->resultJson = $this->createMock(Json::class);
        $resultJsonFactory = $this->createMock(JsonFactory::class);
        $resultJsonFactory->method('create')->willReturn($this->resultJson);
        $serializer = $this->createMock(\Magento\Framework\Serialize\Serializer\Json::class);
        $serializer->method('unserialize')->willReturnMap([
            [self::RAW_BODY, ['data' => self::DATA_STRING, 'mac' => self::MAC]],
            [self::DATA_STRING, self::TRANS_DATA],
        ]);

        $this->processor = $this->createMock(IpnProcessor::class);

        $this->controller = new Ipn(
            $this->request,
            $resultJsonFactory,
            $serializer,
            $this->createMock(Logger::class),
            $this->processor
        );
    }

    // ---- the exact official response contract, per domain outcome ----

    /**
     * Round-3 case 8: a processed callback (SUCCESS outcome) answers the
     * EXACT documented schema — HTTP body `{"return_code":1,
     * "return_message":"Success"}`, nothing else (no mac, no errors field).
     *
     * @return void
     */
    public function testSuccessOutcomeAnswersOfficialContract(): void
    {
        $this->stubPostWithCallback();
        $this->processor->method('process')->willReturn(IpnProcessor::OUTCOME_SUCCESS);
        $this->resultJson->expects($this->once())->method('setData')->with(
            ['return_code' => 1, 'return_message' => 'Success']
        )->willReturnSelf();

        $result = $this->controller->execute();

        $this->assertSame($this->resultJson, $result);
    }

    /**
     * Round-3 case 9: a duplicate callback (ACK outcome) is acknowledged
     * with the documented SUCCESS body so ZaloPay stops retrying.
     *
     * @return void
     */
    public function testDuplicateCallbackAcknowledgedWithSuccessBody(): void
    {
        $this->stubPostWithCallback();
        $this->processor->method('process')->willReturn(IpnProcessor::OUTCOME_ACK_RECONCILIATION);
        $this->resultJson->expects($this->once())->method('setData')->with(
            ['return_code' => 1, 'return_message' => 'Success']
        );

        $this->controller->execute();
    }

    /**
     * Round-3 case 10: a transient failure answers the documented RETRY
     * body (return_code 0 — the official sample's "callback again").
     *
     * @return void
     */
    public function testTransientFailureAnswersDocumentedRetryBody(): void
    {
        $this->stubPostWithCallback();
        $this->processor->method('process')->willReturn(IpnProcessor::OUTCOME_RETRYABLE_FAILURE);
        $this->resultJson->expects($this->once())->method('setData')->with(
            ['return_code' => 0, 'return_message' => 'Temporary failure, please retry.']
        );

        $this->controller->execute();
    }

    /**
     * Round-3 case 11: an invalid callback (MAC failure) answers the
     * documented INVALID body (return_code 2 "Invalid") — never a 500.
     *
     * @return void
     */
    public function testInvalidCallbackAnswersDocumentedInvalidBody(): void
    {
        $this->stubPostWithCallback();
        $this->processor->method('process')->willReturn(IpnProcessor::OUTCOME_INVALID_CALLBACK);
        $this->resultJson->expects($this->once())->method('setData')->with(
            ['return_code' => 2, 'return_message' => 'Invalid']
        );

        $this->controller->execute();
    }

    /**
     * Round-3 case 12: an unknown attempt (the processor's documented
     * "Invalid" policy) is serialized exactly the same documented way.
     *
     * @return void
     */
    public function testUnknownAttemptFollowsDocumentedInvalidPolicy(): void
    {
        $this->stubPostWithCallback();
        $this->processor->method('process')->willReturn(IpnProcessor::OUTCOME_INVALID_CALLBACK);
        $this->resultJson->expects($this->once())->method('setData')->with(
            ['return_code' => 2, 'return_message' => 'Invalid']
        );

        $this->controller->execute();
    }

    /**
     * An unexpected outcome value degrades to the SAFE retryable body —
     * never an undocumented schema, never an error status.
     *
     * @return void
     */
    public function testUnknownOutcomeFallsBackToRetryableBody(): void
    {
        $this->stubPostWithCallback();
        $this->processor->method('process')->willReturn('something-new');
        $this->resultJson->expects($this->once())->method('setData')->with(
            ['return_code' => 0, 'return_message' => 'Temporary failure, please retry.']
        );

        $this->controller->execute();
    }

    /**
     * A processor exception NEVER leaks a 5xx/4xx: the documented retryable
     * body goes out over HTTP 200 (ZaloPay retries; the recovery worker
     * backstops).
     *
     * @return void
     */
    public function testProcessorExceptionStillAnswersDocumentedRetryBody(): void
    {
        $this->stubPostWithCallback();
        $this->processor->method('process')->willThrowException(new \RuntimeException('DB gone away'));
        $this->resultJson->expects($this->once())->method('setData')->with(
            ['return_code' => 0, 'return_message' => 'Temporary failure, please retry.']
        )->willReturnSelf();;

        $this->assertSame($this->resultJson, $this->controller->execute());
    }

    /**
     * The processor receives the callback with `data` decoded into
     * `trans_data` (the parsed claim fields) — the controller's single
     * parsing job.
     *
     * @return void
     */
    public function testProcessorReceivesDecodedCallbackPayload(): void
    {
        $this->request->method('isPost')->willReturn(true);
        $this->request->method('getContent')->willReturn(self::RAW_BODY);
        $this->processor->expects($this->once())->method('process')->with(
            ['data' => self::DATA_STRING, 'mac' => self::MAC, 'trans_data' => self::TRANS_DATA]
        )->willReturn(IpnProcessor::OUTCOME_SUCCESS);

        $this->controller->execute();
    }

    // ---- protocol shape: POST-only, HTTP 200 always, session-free ----

    /**
     * Non-POST probes are refused before any processing — the official
     * callback contract is `Method: POST` (defence in depth behind the
     * removed HttpGetActionInterface).
     *
     * @return void
     */
    public function testNonPostRequestReturnsNullWithoutProcessing(): void
    {
        $this->request->method('isPost')->willReturn(false);
        $this->processor->expects($this->never())->method('process');

        $this->assertNull($this->controller->execute());
    }

    /**
     * Round 3: the controller implements the POST action interface ONLY —
     * HttpGetActionInterface was removed with the legacy protocol.
     *
     * @return void
     */
    public function testControllerIsPostOnly(): void
    {
        $interfaces = class_implements(Ipn::class);

        $this->assertContains(
            \Magento\Framework\App\Action\HttpPostActionInterface::class,
            $interfaces,
            'The official ZaloPay callback is POST.'
        );
        $this->assertArrayNotHasKey(
            \Magento\Framework\App\Action\HttpGetActionInterface::class,
            $interfaces,
            'GET must not be an accepted callback method (official contract is POST-only).'
        );
    }

    /**
     * Round-3 case 28: the IPN controller is SESSION-FREE — no
     * \Magento\Checkout\Model\Session (or any session class) in its
     * constructor: the server-to-server path must never touch browser
     * state.
     *
     * @return void
     */
    public function testControllerIsSessionFree(): void
    {
        $parameters = (new \ReflectionClass(Ipn::class))->getConstructor()->getParameters();
        $types = array_map(
            static fn (\ReflectionParameter $parameter): string => (string)$parameter->getType(),
            $parameters
        );

        $this->assertNotContains(\Magento\Checkout\Model\Session::class, $types);
        foreach ($types as $type) {
            $this->assertStringNotContainsString('Session', $type, 'IPN must stay session-free.');
        }
    }

    /**
     * @return void
     */
    private function stubPostWithCallback(): void
    {
        $this->request->method('isPost')->willReturn(true);
        $this->request->method('getContent')->willReturn(self::RAW_BODY);
    }
}
