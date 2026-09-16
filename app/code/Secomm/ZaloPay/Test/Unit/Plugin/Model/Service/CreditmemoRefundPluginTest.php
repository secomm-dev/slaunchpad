<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Plugin\Model\Service;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\CreditmemoService;
use Secomm\ZaloPay\Exception\RefundTransportException;
use Secomm\ZaloPay\Gateway\Command\RefundCommand;
use Secomm\ZaloPay\Gateway\Command\RefundOutcome;
use Magento\Framework\Exception\LocalizedException;
use Secomm\ZaloPay\Plugin\Model\Service\CreditmemoRefundPlugin;
use Secomm\ZaloPay\Service\CreditmemoRefundPreflight;
use Secomm\ZaloPay\Service\PendingRefundManager;
use Secomm\ZaloPay\Service\RefundOutcomeMarker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * TASK-CG6BM7 BLOCKER 1: admin entry-point guard + lifecycle decision
 * matrix for the refund lifecycle orchestrator.
 */
class CreditmemoRefundPluginTest extends TestCase
{
    private MethodInterface|MockObject $method;

    private PaymentDataObjectFactory|MockObject $paymentDataObjectFactory;

    private RefundCommand|MockObject $mockRefundCommand;

    private PendingRefundManager|MockObject $mockRefundManager;

    private CreditmemoRefundPreflight|MockObject $preflight;

    private RefundOutcomeMarker $marker;

    private ManagerInterface|MockObject $messageManager;

    private CreditmemoRefundPlugin $plugin;

    private \Magento\Sales\Model\Order\Creditmemo|MockObject $creditmemo;

    private Order|MockObject $order;

    private \Magento\Sales\Model\Order\Payment|MockObject $payment;

    private Invoice|MockObject|null $invoice = null;

    private string $paymentMethod = 'secomm_zalopay';

    private ?string $invoiceTxnId = 'CAPTURE-19';

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->method = $this->createMock(MethodInterface::class);
        $this->method->method('getCode')->willReturn('secomm_zalopay');
        $this->paymentDataObjectFactory = $this->createMock(PaymentDataObjectFactory::class);
        $this->mockRefundCommand = $this->createMock(RefundCommand::class);
        $this->mockRefundManager = $this->createMock(PendingRefundManager::class);
        $this->preflight = $this->createMock(CreditmemoRefundPreflight::class);
        $this->marker = new RefundOutcomeMarker();
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->plugin = new CreditmemoRefundPlugin(
            $this->method,
            $this->paymentDataObjectFactory,
            $this->mockRefundCommand,
            $this->mockRefundManager,
            $this->marker,
            $this->messageManager,
            $this->preflight
        );

        $this->payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $this->payment->method('getMethod')->willReturnCallback(fn (): string => $this->paymentMethod);
        $this->order = $this->createMock(Order::class);
        $this->order->method('getPayment')->willReturn($this->payment);
        $this->order->method('getId')->willReturn(77);
        $this->creditmemo = $this->createMock(\Magento\Sales\Model\Order\Creditmemo::class);
        $this->creditmemo->method('getOrder')->willReturn($this->order);
        $this->creditmemo->method('getBaseGrandTotal')->willReturn(25.0);
        $this->creditmemo->method('getInvoice')->willReturnCallback(fn () => $this->invoice);
        $this->invoice = $this->createMock(Invoice::class);
        $this->invoice->method('getTransactionId')->willReturnCallback(fn (): ?string => $this->invoiceTxnId);
    }

    private function proceedSpy(string $sentinel = 'CORE-RESULT', bool $offline = false): array
    {
        $calls = [];

        return [
            'callable' => function ($cm = null, $off = null) use (&$calls, $sentinel) {
                $calls[] = [$cm, $off];

                return $sentinel;
            },
            'calls' => &$calls,
        ];
    }
    /**
     * GATE: non-ZaloPay refunds flow through the core untouched.
     */
    public function testNonZaloPayPassesThrough(): void
    {
        $this->paymentMethod = 'momo';
        $spy = $this->proceedSpy();
        $result = $this->plugin->aroundRefund(
            $this->createMock(CreditmemoService::class),
            $spy['callable'],
            $this->creditmemo
        );
        $this->assertSame('CORE-RESULT', $result);
        $this->assertSame([[$this->creditmemo, false]], $spy['calls']);
    }
    /**
     * GATE: offline refunds are refused - the provider must be informed.
     */
    public function testOfflineRefundRefused(): void
    {
        $this->mockRefundCommand->expects($this->never())->method('execute');
        $this->mockRefundManager->expects($this->never())->method('registerPending');
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo,
                true
            );
            self::fail('offline refund must be refused');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('Offline refunds are not supported', $exception->getMessage());
        }
        $this->assertSame([], $spy['calls']);
    }

    /**
     * GATE: a second refund while one is still being reconciled is blocked
     * (the refundable balance does not yet reflect the pending refund).
     */
    public function testInFlightRefundBlocked(): void
    {
        $this->mockRefundManager->method('hasInFlight')->willReturn(true);
        $this->mockRefundCommand->expects($this->never())->method('execute');
        $this->mockRefundManager->expects($this->never())->method('registerPending');
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('in-flight refund must block');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('still being reconciled', $exception->getMessage());
        }
        $this->assertSame([], $spy['calls']);
    }
    /**
     * GATE: refund without an invoice (no capture transaction) is refused.
     */
    public function testMissingInvoiceRefused(): void
    {
        $this->invoice = null;
        $this->mockRefundCommand->expects($this->never())->method('execute');
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('missing invoice must be refused');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('original invoice transaction', $exception->getMessage());
        }
        $this->assertSame([], $spy['calls']);
    }

    /**
     * GATE: invoice without a transaction id is refused too.
     */
    public function testInvoiceWithoutTransactionIdRefused(): void
    {
        $this->invoiceTxnId = '';
        $this->mockRefundCommand->expects($this->never())->method('execute');
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('missing invoice transaction must be refused');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('original invoice transaction', $exception->getMessage());
        }
        $this->assertSame([], $spy['calls']);
    }
    /**
     * BLOCKER 1 lifecycle: PROCESSING registers the durable pending track
     * and STOPS before the core refund accounting - total_refunded and
     * qty_refunded stay untouched, the order cannot become CLOSED.
     */
    public function testProcessingRegistersPendingAndStopsBeforeCore(): void
    {
        $outcome = new RefundOutcome(
            RefundOutcome::STATUS_PROCESSING,
            '260916_1000_777_r1',
            25000,
            '{"app_id":1}'
        );
        $this->paymentDataObjectFactory->expects($this->once())->method('create')
            ->with($this->identicalTo($this->payment))
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->expects($this->once())->method('execute')
            ->with($this->callback(function (array $subject): bool {
                return ($subject['amount'] ?? null) === 25.0
                    && isset($subject['payment']);
            }))
            ->willReturn($outcome);
        $this->payment->expects($this->once())->method('setCreditmemo')
            ->with($this->identicalTo($this->creditmemo));
        $this->payment->expects($this->once())->method('setParentTransactionId')->with('CAPTURE-19');
        $this->mockRefundManager->expects($this->once())->method('registerPending')
            ->with(
                $this->identicalTo($this->creditmemo),
                $this->identicalTo($outcome)
            );
        $this->messageManager->expects($this->once())->method('addSuccessMessage')
            ->with($this->callback(function ($msg): bool {
                return str_contains((string)$msg, 'finalized automatically');
            }));
        $spy = $this->proceedSpy();
        $result = $this->plugin->aroundRefund(
            $this->createMock(CreditmemoService::class),
            $spy['callable'],
            $this->creditmemo
        );
        $this->assertSame($this->creditmemo, $result);
        $this->assertSame([], $spy['calls']);
    }
    /**
     * BLOCKER 1 lifecycle: confirmed SUCCESS marks the known outcome and
     * lets the NATIVE core accounting run exactly once (the gateway refund
     * command skips its provider call via the marker).
     */
    public function testSuccessMarksOutcomeAndRunsCoreFlow(): void
    {
        $outcome = new RefundOutcome(RefundOutcome::STATUS_SUCCESS, '260916_1000_777_r2', 25000, null);
        $this->paymentDataObjectFactory->expects($this->once())->method('create')
            ->with($this->identicalTo($this->payment))
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->expects($this->once())->method('execute')->willReturn($outcome);
        $this->mockRefundManager->expects($this->never())->method('registerPending');
        $spy = $this->proceedSpy('CORE-RESULT-SUCCESS');
        $result = $this->plugin->aroundRefund(
            $this->createMock(CreditmemoService::class),
            $spy['callable'],
            $this->creditmemo
        );
        $this->assertSame('CORE-RESULT-SUCCESS', $result);
        $this->assertSame([[$this->creditmemo, false]], $spy['calls']);
        $this->assertTrue($this->marker->isProviderAlreadyAsked(77));
    }

    /**
     * Defensive: null outcome (marker already claimed) hands over to the
     * core flow unchanged.
     */
    public function testNullOutcomePassesThroughToCore(): void
    {
        $this->mockRefundCommand->expects($this->once())->method('execute')->willReturn(null);
        $spy = $this->proceedSpy('CORE-RESULT-NULL');
        $result = $this->plugin->aroundRefund(
            $this->createMock(CreditmemoService::class),
            $spy['callable'],
            $this->creditmemo
        );
        $this->assertSame('CORE-RESULT-NULL', $result);
        $this->assertSame([[$this->creditmemo, false]], $spy['calls']);
        $this->assertFalse($this->marker->isProviderAlreadyAsked(77));
    }
    /**
     * BLOCKER 1 lifecycle: transport failure WITH a tracking outcome
     * registers the durable pending track (reconciled by m_refund_id -
     * never re-requested) and surfaces an honest, retryable error.
     */
    public function testTransportWithOutcomeRegistersPendingAndThrows(): void
    {
        $tracking = new RefundOutcome(RefundOutcome::STATUS_PROCESSING, '260916_1000_777_r3', 25000, '{"app_id":1}');
        $transport = new RefundTransportException(
            new \Magento\Framework\Phrase('Zalopay: Refund failed. Please try again later.'),
            null,
            $tracking
        );
        $this->mockRefundCommand->expects($this->once())->method('execute')->willThrowException($transport);
        $this->mockRefundManager->expects($this->once())->method('registerPending')
            ->with(
                $this->identicalTo($this->creditmemo),
                $this->identicalTo($tracking),
                'transport_error: initial refund outcome unknown - reconciliation pending'
            );
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('unconfirmed transport outcome must surface an error');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('tracked and will be reconciled automatically', $exception->getMessage());
            $this->assertStringNotContainsString('CURL', $exception->getMessage());
        }
        $this->assertSame([], $spy['calls']);
    }

    /**
     * BLOCKER 2 classification: transport failure WITHOUT a tracking
     * outcome (request identity never built) is retryable - nothing is
     * durably tracked, a customer-safe retryable error is surfaced.
     */
    public function testUntrackableTransportThrowsRetryableError(): void
    {
        $transport = new RefundTransportException(
            new \Magento\Framework\Phrase('Zalopay: Refund failed. Please try again later.')
        );
        $this->mockRefundCommand->expects($this->once())->method('execute')->willThrowException($transport);
        $this->mockRefundManager->expects($this->never())->method('registerPending');
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('untrackable transport failure must surface an error');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('Refund failed. Please try again later.', $exception->getMessage());
        }
        $this->assertSame([], $spy['calls']);
    }

    /**
     * BLOCKER 1 lifecycle: provider FAIL propagates untouched - nothing is
     * persisted, totals untouched, the core accounting never runs.
     */
    public function testProviderFailPropagatesUntouched(): void
    {
        $refusal = new LocalizedException(new \Magento\Framework\Phrase('Zalopay: Refund failed.'));
        $this->mockRefundCommand->expects($this->once())->method('execute')->willThrowException($refusal);
        $this->mockRefundManager->expects($this->never())->method('registerPending');
        $spy = $this->proceedSpy();
        $caught = null;
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('provider refusal must propagate');
        } catch (LocalizedException $exception) {
            $caught = $exception;
        }
        $this->assertSame($refusal, $caught);
        $this->assertSame([], $spy['calls']);
    }
    /**
     * BLOCKER round 2: Magento validation BEFORE provider. An over-refund
     * (core mirror check 3) rejects INSIDE the preflight - the provider is
     * NEVER called, nothing is registered pending, core never runs.
     */
    public function testOverRefundProviderNeverCalled(): void
    {
        $this->preflight->method('validateRefundable')
            ->willThrowException(new LocalizedException(
                new \Magento\Framework\Phrase('The most money available to refund is 0.00.')
            ));
        $this->mockRefundCommand->expects($this->never())->method('execute');
        $this->mockRefundManager->expects($this->never())->method('registerPending');
        $this->paymentDataObjectFactory->expects($this->never())->method('create');
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('over-refund must be rejected before the provider');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('most money available to refund', $exception->getMessage());
        }
        $this->assertSame([], $spy['calls']);
    }

    /**
     * BLOCKER round 2: an already-processed (non-open) EXISTING credit memo
     * is rejected by the core-mirror check - provider NEVER called.
     */
    public function testNonOpenCreditmemoProviderNeverCalled(): void
    {
        $this->preflight->method('validateRefundable')
            ->willThrowException(new LocalizedException(
                new \Magento\Framework\Phrase('We cannot register an existing credit memo.')
            ));
        $this->mockRefundCommand->expects($this->never())->method('execute');
        $this->mockRefundManager->expects($this->never())->method('registerPending');
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('non-open creditmemo must be rejected before the provider');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('existing credit memo', $exception->getMessage());
        }
        $this->assertSame([], $spy['calls']);
    }

    /**
     * BLOCKER round 2: an invalid order reference is rejected by the
     * core-mirror check - provider NEVER called.
     */
    public function testInvalidOrderProviderNeverCalled(): void
    {
        $this->preflight->method('validateRefundable')
            ->willThrowException(new NoSuchEntityException(
                new \Magento\Framework\Phrase('We found an invalid order to refund.')
            ));
        $this->mockRefundCommand->expects($this->never())->method('execute');
        $this->mockRefundManager->expects($this->never())->method('registerPending');
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('invalid order must be rejected before the provider');
        } catch (NoSuchEntityException $exception) {
            $this->assertStringContainsString('invalid order', $exception->getMessage());
        }
        $this->assertSame([], $spy['calls']);
    }

    /**
     * BLOCKER round 2: a zero/negative online refund amount is rejected by
     * the supplementary preflight check - provider NEVER called.
     */
    public function testZeroOnlineAmountProviderNeverCalled(): void
    {
        $this->preflight->method('validateRefundable')
            ->willThrowException(new LocalizedException(
                new \Magento\Framework\Phrase('Zalopay: The online refund amount must be greater than zero.')
            ));
        $this->mockRefundCommand->expects($this->never())->method('execute');
        $this->mockRefundManager->expects($this->never())->method('registerPending');
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('zero amount must be rejected before the provider');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('greater than zero', $exception->getMessage());
        }
        $this->assertSame([], $spy['calls']);
    }

    /**
     * BLOCKER round 2 ordering guarantee: the preflight runs BEFORE the
     * provider call on the happy path too - a valid refund asks the provider
     * exactly once, after validation passed.
     */
    public function testValidRefundValidatedBeforeProviderOnce(): void
    {
        $order = [];
        $this->preflight->expects($this->once())->method('validateRefundable')
            ->willReturnCallback(function () use (&$order) {
                $order[] = 'preflight';

                return null;
            });
        $this->paymentDataObjectFactory->method('create')
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->expects($this->once())->method('execute')
            ->willReturnCallback(function () use (&$order) {
                $order[] = 'provider';

                return new RefundOutcome(RefundOutcome::STATUS_PROCESSING, '260916_1000_777_r9', 25000, null);
            });
        $this->mockRefundManager->method('registerPending');
        $this->plugin->aroundRefund(
            $this->createMock(CreditmemoService::class),
            $this->proceedSpy()['callable'],
            $this->creditmemo
        );
        $this->assertSame(['preflight', 'provider'], $order);
    }
}
