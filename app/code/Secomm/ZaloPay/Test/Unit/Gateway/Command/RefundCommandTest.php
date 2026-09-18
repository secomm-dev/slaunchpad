<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Gateway\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Exception\RefundProtocolException;
use Secomm\ZaloPay\Exception\RefundTransportException;
use Secomm\ZaloPay\Gateway\Command\RefundCommand;
use Secomm\ZaloPay\Gateway\Command\RefundOutcome;
use Secomm\ZaloPay\Gateway\Command\RefundQueryCommand;
use Secomm\ZaloPay\Gateway\Helper\Rate;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Service\RefundOutcomeMarker;

/**
 * REFUND CMD 16-23 (TASK-CG6BM7 corrective round 7): the provider-only
 * RefundCommand contract:
 *
 *  - one-shot marker authorization for THIS credit memo -> provider NEVER
 *    asked (null, skip path, consumed exactly once; another credit memo on
 *    the same order still builds a request - F28);
 *  - rc 1                      -> SUCCESS outcome + response handler runs;
 *  - rc 3 -> query 1           -> SUCCESS outcome, handler sees the ORIGINAL
 *    v2/refund response;
 *  - rc 3 -> query 3/unknown/anomaly/transport -> PROCESSING outcome
 *    carrying the serialized v2/query_refund payload (durable track, no
 *    throw - the refund IS accepted by rc=3);
 *  - rc 3 -> query 2           -> LocalizedException, safe provider-map message;
 *  - rc 2                      -> LocalizedException (the ONLY confirmed-
 *    refusal path), safe provider-map message;
 *  - return_code missing / non-numeric / outside {1,2,3} -> RefundProtocolException
 *    (NOT a refusal - F30);
 *  - transport failure         -> RefundTransportException carrying the
 *    PROCESSING tracking outcome (m_refund_id + payload) so the caller can
 *    track the refund durably and never re-request it;
 *  - raw provider text NEVER reaches any user-facing message.
 */
class RefundCommandTest extends TestCase
{
    private const M_REFUND_ID = '260916_1000_777';

    private const ORDER_ID = 7;

    private const CREDIT_MEMO_ID = 555;

    private BuilderInterface|MockObject $requestBuilder;

    private TransferFactoryInterface|MockObject $transferFactory;

    private ClientInterface|MockObject $client;

    private Logger|MockObject $logger;

    private RefundQueryCommand|MockObject $refundQueryCommand;

    private Rate|MockObject $rate;

    private HandlerInterface|MockObject $handler;

    private OrderPayment|MockObject $payment;

    private Creditmemo|MockObject $creditmemo;

    private Order|MockObject $order;

    private RefundOutcomeMarker $outcomeMarker;

    private RefundCommand $command;

    /**
     * Mutable test state: the credit memo entity id the payment currently
     * carries (the marker is keyed by THIS id - round 7 F28).
     */
    private int $creditmemoEntityId = self::CREDIT_MEMO_ID;

    protected function setUp(): void
    {
        $this->requestBuilder = $this->createMock(BuilderInterface::class);
        $this->transferFactory = $this->createMock(TransferFactoryInterface::class);
        $this->client = $this->createMock(ClientInterface::class);
        $this->logger = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $this->refundQueryCommand = $this->createMock(RefundQueryCommand::class);
        $this->rate = $this->createMock(Rate::class);
        $this->handler = $this->createMock(HandlerInterface::class);

        $this->payment = $this->createMock(OrderPayment::class);
        $this->creditmemo = $this->createMock(Creditmemo::class);
        $this->order = $this->createMock(Order::class);

        $this->payment->method('getCreditmemo')->willReturn($this->creditmemo);
        $this->creditmemo->method('getOrder')->willReturn($this->order);
        $this->creditmemo->method('getEntityId')->willReturnCallback(fn (): int => $this->creditmemoEntityId);
        $this->order->method('getId')->willReturn(self::ORDER_ID);

        $this->rate->method('getVndAmount')->willReturn(50000.0);

        $this->requestBuilder->method('build')->willReturn($this->refundRequest());
        $this->transferFactory->method('create')->willReturn($this->createMock(TransferInterface::class));
        $this->refundQueryCommand->method('setZaloRefundId')->willReturnSelf();
        $this->refundQueryCommand->method('buildRequestData')->willReturn($this->queryRequest());

        $this->outcomeMarker = new RefundOutcomeMarker();

        $this->command = new RefundCommand(
            $this->requestBuilder,
            $this->transferFactory,
            $this->client,
            $this->logger,
            $this->refundQueryCommand,
            $this->rate,
            new Json(),
            $this->outcomeMarker,
            $this->handler
        );
    }

    /**
     * @return array
     */
    private function refundRequest(): array
    {
        return [
            'app_id' => '1000',
            RefundInterface::M_REFUND_ID => self::M_REFUND_ID,
            'zp_trans_id' => 'ZP-ORIGINAL',
            'amount' => 50000,
            'description' => 'Refund for order #000000007',
        ];
    }

    /**
     * @return array
     */
    private function queryRequest(): array
    {
        return [
            'app_id' => '1000',
            RefundInterface::M_REFUND_ID => self::M_REFUND_ID,
            'timestamp' => 1690000000000,
        ];
    }

    /**
     * @return array
     */
    private function commandSubject(): array
    {
        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($this->payment);
        $paymentDO->method('getOrder')->willReturn($this->order);

        return ['payment' => $paymentDO, 'amount' => 100.0];
    }

    /**
     * REFUND CMD 16: rc 1 -> SUCCESS outcome, handler runs, VND amount from
     * the rate helper (identical arithmetic to the request builder).
     */
    public function testSuccessResponseReturnsSuccessOutcome(): void
    {
        $this->client->expects($this->once())->method('placeRequest')->willReturn(
            ['return_code' => 1, 'return_message' => 'Refund successful.']
        );
        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->commandSubject(), $this->anything());

        $outcome = $this->command->execute($this->commandSubject());

        $this->assertNotNull($outcome);
        $this->assertSame(RefundOutcome::STATUS_SUCCESS, $outcome->getStatus());
        $this->assertSame(self::M_REFUND_ID, $outcome->getMRefundId());
        $this->assertSame(50000, $outcome->getVndAmount());
        $this->assertNull($outcome->getQueryPayload());
    }

    /**
     * REFUND CMD 17: rc 3 -> immediate query says 1 -> SUCCESS outcome and
     * the handler sees the ORIGINAL v2/refund response.
     */
    public function testProcessingThenQuerySuccessFinalizesOutcome(): void
    {
        $refundResponse = ['return_code' => 3, 'return_message' => 'processing', 'refund_id' => 'RF1'];
        $this->client->expects($this->once())->method('placeRequest')->willReturn($refundResponse);
        $this->refundQueryCommand->expects($this->once())
            ->method('getRefundQuery')
            ->willReturn(['return_code' => 1, 'return_message' => 'Refund successful.']);
        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->anything(), $this->identicalTo($refundResponse));

        $outcome = $this->command->execute($this->commandSubject());

        $this->assertNotNull($outcome);
        $this->assertSame(RefundOutcome::STATUS_SUCCESS, $outcome->getStatus());
    }

    /**
     * REFUND CMD 18: rc 3 -> query still 3 -> PROCESSING outcome carrying the
     * serialized query payload (durable track). No exception reaches core.
     */
    public function testProcessingThenProcessingStaysTracked(): void
    {
        $this->client->method('placeRequest')->willReturn(['return_code' => 3, 'return_message' => 'processing']);
        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 3, 'return_message' => 'processing']
        );

        $outcome = $this->command->execute($this->commandSubject());

        $this->assertNotNull($outcome);
        $this->assertSame(RefundOutcome::STATUS_PROCESSING, $outcome->getStatus());
        $payload = $outcome->getQueryPayload();
        $this->assertIsString($payload);
        $decoded = json_decode((string)$payload, true);
        $this->assertSame(self::M_REFUND_ID, $decoded[RefundInterface::M_REFUND_ID] ?? null);
    }

    /**
     * REFUND CMD 18b: rc 3 -> query transport failure -> still PROCESSING
     * (the refund IS accepted by the provider - it must be tracked, never
     * re-requested).
     */
    public function testProcessingThenQueryTransportStaysTracked(): void
    {
        $this->client->method('placeRequest')->willReturn(['return_code' => 3, 'return_message' => 'processing']);
        $this->refundQueryCommand->method('getRefundQuery')->willThrowException(
            new RefundTransportException(__('Zalopay: Refund status could not be confirmed.'))
        );

        $outcome = $this->command->execute($this->commandSubject());

        $this->assertNotNull($outcome);
        $this->assertSame(RefundOutcome::STATUS_PROCESSING, $outcome->getStatus());
        $this->assertNotNull($outcome->getQueryPayload());
        $this->handler->expects($this->never())->method('handle');
    }

    /**
     * REFUND CMD 19: rc 3 -> query says FAIL -> LocalizedException with the
     * safe provider-map message (Payment::refund's caught type).
     */
    public function testProcessingThenQueryFailThrowsSafeMappedMessage(): void
    {
        $this->client->method('placeRequest')->willReturn(['return_code' => 3, 'return_message' => 'processing']);
        $this->refundQueryCommand->method('getRefundQuery')->willReturn(
            ['return_code' => 2, 'sub_return_code' => -13, 'return_message' => 'RAW-PROVIDER-DETAIL']
        );

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Zalopay: ');

        $this->command->execute($this->commandSubject());
    }

    /**
     * REFUND CMD 20: rc 2 -> LocalizedException with the mapped message; the
     * raw provider detail never reaches the message.
     */
    public function testFailResponseThrowsLocalizedWithMappedMessage(): void
    {
        $this->client->expects($this->once())->method('placeRequest')->willReturn(
            ['return_code' => 2, 'sub_return_code' => -13, 'return_message' => 'RAW-PROVIDER-DETAIL']
        );

        try {
            $this->command->execute($this->commandSubject());
            $this->fail('Expected LocalizedException');
        } catch (LocalizedException $exception) {
            $this->assertSame('Zalopay: Refund failed.', (string)$exception->getMessage());
            $this->assertStringNotContainsString('RAW-PROVIDER-DETAIL', (string)$exception->getMessage());
        }
    }

    /**
     * REFUND CMD 21 (round 7 F30): a MISSING return_code is a protocol
     * anomaly -> RefundProtocolException (never a crash, never a false
     * success, never folded into the confirmed-refusal path) with the safe
     * "not confirmable" message, and the anomaly is logged.
     */
    public function testMissingReturnCodeThrowsProtocolException(): void
    {
        $this->client->expects($this->once())->method('placeRequest')->willReturn(
            ['unexpected' => 'payload']
        );
        $this->logger->expects($this->once())->method('error')
            ->with($this->stringContains('missing or non-numeric return_code'));

        try {
            $this->command->execute($this->commandSubject());
            $this->fail('Expected RefundProtocolException');
        } catch (RefundProtocolException $exception) {
            $this->assertStringContainsString('Refund status could not be confirmed', $exception->getMessage());
        }
    }

    /**
     * Round 7 F30: a NON-NUMERIC return_code is a protocol anomaly too.
     */
    public function testNonNumericReturnCodeThrowsProtocolException(): void
    {
        $this->client->expects($this->once())->method('placeRequest')->willReturn(
            ['return_code' => 'abc', 'return_message' => 'garbled envelope']
        );
        $this->logger->expects($this->once())->method('error')
            ->with($this->stringContains('missing or non-numeric return_code'));

        $this->expectException(RefundProtocolException::class);
        $this->expectExceptionMessage('Zalopay: ');

        $this->command->execute($this->commandSubject());
    }

    /**
     * Round 7 F30: a numeric return_code OUTSIDE {1,2,3} (e.g. 999) is an
     * unexpected-provider-code anomaly -> RefundProtocolException - NOT a
     * confirmed refusal, so the caller must land UNKNOWN (never
     * confirmed_fail) and reconcile by m_refund_id.
     */
    public function testUnexpectedReturnCodeThrowsProtocolException(): void
    {
        $this->client->expects($this->once())->method('placeRequest')->willReturn(
            ['return_code' => 999, 'return_message' => 'unknown provider state']
        );
        $this->logger->expects($this->once())->method('error')
            ->with($this->stringContains('unexpected provider return_code=999'));

        try {
            $this->command->execute($this->commandSubject());
            $this->fail('Expected RefundProtocolException');
        } catch (RefundProtocolException $exception) {
            $this->assertStringContainsString('Refund status could not be confirmed', $exception->getMessage());
            $this->assertStringNotContainsString('999', $exception->getMessage());
        }
    }

    /**
     * REFUND CMD 20b (round 7 F30): rc 2 IS the ONE confirmed-refusal path
     * and stays a PLAIN LocalizedException (provider-map message) - never a
     * RefundProtocolException, so the caller lands confirmed_fail.
     */
    public function testRefusedReturnCodeIsNotAProtocolAnomaly(): void
    {
        $this->client->expects($this->once())->method('placeRequest')->willReturn(
            ['return_code' => 2, 'sub_return_code' => -13, 'return_message' => 'RAW-PROVIDER-DETAIL']
        );

        try {
            $this->command->execute($this->commandSubject());
            $this->fail('Expected LocalizedException');
        } catch (RefundProtocolException $exception) {
            $this->fail('rc=2 is a confirmed refusal, not a protocol anomaly');
        } catch (LocalizedException $exception) {
            $this->assertSame('Zalopay: Refund failed.', (string)$exception->getMessage());
        }
    }

    /**
     * Round 7 F30: rc 3 + immediate query with an ANOMALOUS (missing)
     * return_code stays PROCESSING - the refund IS accepted by rc=3, so the
     * anomaly in the query response must never downgrade it to a refusal.
     */
    public function testProcessingThenQueryAnomalyStaysTracked(): void
    {
        $this->client->method('placeRequest')->willReturn(['return_code' => 3, 'return_message' => 'processing']);
        $this->refundQueryCommand->method('getRefundQuery')->willReturn(['garbled' => 'query envelope']);

        $outcome = $this->command->execute($this->commandSubject());

        $this->assertNotNull($outcome);
        $this->assertSame(RefundOutcome::STATUS_PROCESSING, $outcome->getStatus());
        $this->handler->expects($this->never())->method('handle');
    }

    /**
     * REFUND CMD 22: transport failure -> RefundTransportException carrying
     * the PROCESSING tracking outcome (identity + payload) - the caller
     * tracks the refund durably; raw transport text never reaches the
     * message.
     */
    public function testTransportFailureCarriesTrackingOutcome(): void
    {
        $this->client->expects($this->once())->method('placeRequest')->willThrowException(
            new \RuntimeException('CURL transport error 28: connection timed out')
        );

        try {
            $this->command->execute($this->commandSubject());
            $this->fail('Expected RefundTransportException');
        } catch (RefundTransportException $exception) {
            $this->assertSame('Zalopay: Refund failed. Please try again later.', (string)$exception->getMessage());
            $this->assertStringNotContainsString('CURL', (string)$exception->getMessage());

            $outcome = $exception->getOutcome();
            $this->assertNotNull($outcome);
            $this->assertSame(RefundOutcome::STATUS_PROCESSING, $outcome->getStatus());
            $this->assertSame(self::M_REFUND_ID, $outcome->getMRefundId());
            $payload = $outcome->getQueryPayload();
            $this->assertIsString($payload);
            $decoded = json_decode((string)$payload, true);
            $this->assertSame(self::M_REFUND_ID, $decoded[RefundInterface::M_REFUND_ID] ?? null);
        }
    }

    /**
     * REFUND CMD 22b: a query-payload build failure does not abort the
     * provider call; on rc 3 the refund is STILL tracked (PROCESSING) with a
     * null payload (cron terminal-reconciles such a row with evidence).
     */
    public function testPayloadBuildFailureStillTracksWithoutPayload(): void
    {
        $this->refundQueryCommand->method('buildRequestData')->willThrowException(
            new \RuntimeException('rate unavailable')
        );
        $this->client->method('placeRequest')->willReturn(
            ['return_code' => AbstractResponseValidator::REFUND_PROCESSING, 'return_message' => 'processing']
        );

        $outcome = $this->command->execute($this->commandSubject());

        $this->assertNotNull($outcome);
        $this->assertSame(RefundOutcome::STATUS_PROCESSING, $outcome->getStatus());
        $this->assertNull($outcome->getQueryPayload());
    }

    /**
     * REFUND CMD 23 (round 7 F28): a consumed one-shot authorization for
     * THIS credit memo means core accounting is finalizing a locally
     * tracked refund - the provider is NEVER asked (null, no request
     * built or sent) EXACTLY once; a second attempt for the same credit
     * memo is no longer authorized (fail-closed: the caller aborts, the
     * provider would be asked).
     */
    public function testProviderSkipAuthorizationIsConsumedExactlyOnce(): void
    {
        $this->outcomeMarker->authorize(self::CREDIT_MEMO_ID);

        // First finalize pass: the authorization is consumed, provider skip.
        $this->requestBuilder->expects($this->never())->method('build');
        $this->client->expects($this->never())->method('placeRequest');
        $this->handler->expects($this->never())->method('handle');

        $this->assertNull($this->command->execute($this->commandSubject()));

        // The one-shot pin is gone: a second run for the SAME credit memo is
        // NOT authorized any more (fail-closed).
        $this->assertFalse($this->outcomeMarker->consume(self::CREDIT_MEMO_ID));
    }

    /**
     * REFUND CMD 23 (round 7 F28): the marker is keyed by CREDIT MEMO id -
     * after refund A's authorization was consumed, a refund B on the SAME
     * order (different credit memo) still builds a normal provider request.
     */
    public function testSkipAuthorizationDoesNotLeakToAnotherCreditMemoOnSameOrder(): void
    {
        $this->outcomeMarker->authorize(self::CREDIT_MEMO_ID);
        $this->assertNull($this->command->execute($this->commandSubject()));

        // Refund B: same order 7, DIFFERENT credit memo 556.
        $this->creditmemoEntityId = 556;
        $this->requestBuilder->expects($this->once())->method('build');
        $this->client->expects($this->once())->method('placeRequest')->willReturn(
            ['return_code' => 1, 'return_message' => 'Refund successful.']
        );

        $outcome = $this->command->execute($this->commandSubject());

        $this->assertNotNull($outcome);
        $this->assertSame(RefundOutcome::STATUS_SUCCESS, $outcome->getStatus());
    }

    /**
     * REFUND CMD 23c (round 7 F28): at the prepare() level - the first
     * prepare for an authorized credit memo returns null (authorized skip),
     * the second prepare for ANOTHER credit memo builds a request.
     */
    public function testPrepareAuthorizedSkipThenAnotherCreditmemoBuildsRequest(): void
    {
        $this->outcomeMarker->authorize(self::CREDIT_MEMO_ID);
        $this->assertNull($this->command->prepare($this->commandSubject()));

        $this->creditmemoEntityId = 557;
        $request = $this->command->prepare($this->commandSubject());

        $this->assertNotNull($request);
        $this->assertSame(self::M_REFUND_ID, $request->getMRefundId());
    }

    /**
     * REFUND CMD 23b: a payment without an attached creditmemo is refused
     * defensively (the plugin and Payment::refund always attach one).
     */
    public function testMissingCreditmemoOnPaymentRefusesRefund(): void
    {
        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getCreditmemo')->willReturn(null);
        $paymentDO->method('getPayment')->willReturn($payment);

        $this->client->expects($this->never())->method('placeRequest');

        $this->expectException(LocalizedException::class);

        $this->command->execute(['payment' => $paymentDO, 'amount' => 100.0]);
    }
}
