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
use Secomm\ZaloPay\Exception\RefundProtocolException;
use Secomm\ZaloPay\Exception\RefundTransportException;
use Secomm\ZaloPay\Gateway\Command\RefundCommand;
use Secomm\ZaloPay\Gateway\Command\RefundOutcome;
use Secomm\ZaloPay\Gateway\Command\RefundRequest;
use Magento\Framework\Exception\LocalizedException;
use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Plugin\Model\Service\CreditmemoRefundPlugin;
use Secomm\ZaloPay\Service\CreditmemoRefundPreflight;
use Secomm\ZaloPay\Service\PendingRefundManager;
use Secomm\ZaloPay\Service\RefundOutcomeMarker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * TASK-CG6BM7 BLOCKER 1: admin entry-point guard + lifecycle decision
 * matrix for the refund lifecycle orchestrator (round 7: F27 pre-provider
 * STATE_OPEN + F31 invoice_id pinning + F28 one-shot fail-closed marker +
 * F30 protocol anomaly classification + F34 no custom credit memo state).
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

    private \Magento\Sales\Api\CreditmemoRepositoryInterface|MockObject $creditmemoRepository;

    private Logger|MockObject $logger;

    /**
     * Mutable test state: the credit memo entity_id (NULL = the REAL admin
     * flow presents an UNSAVED credit memo; the repository save mock
     * assigns the real id - tests must never pre-stub it, round 4 F17).
     */
    private ?int $creditmemoEntityId = null;

    /**
     * Mutable test state: the credit memo state as observed at the moment
     * of the pre-provider repository save (round 7 F27 pin).
     */
    private ?int $stateAtSave = null;

    /**
     * Mutable test state: the credit memo invoice_id as observed at the
     * moment of the pre-provider repository save (round 7 F31 pin).
     */
    private ?int $invoiceIdAtSave = null;

    /**
     * Mutable test state: the bindCreditMemo outcome (overridden by the
     * bind-failure test - a mutable flag avoids double-config ambiguity
     * between the setUp stub and a per-test stub, which PHPUnit 10.5 does
     * NOT resolve in favor of the later configuration).
     */
    private bool $bindResult = true;

    /**
     * Mutable test state: the markProviderRequestStarted outcome (round 5
     * F23) - same double-config discipline as bindResult.
     */
    private bool $providerStartResult = true;

    private \Magento\Sales\Model\Order\Creditmemo|MockObject $creditmemo;

    private Order|MockObject $order;

    private \Magento\Sales\Model\Order\Payment|MockObject $payment;

    private Invoice|MockObject|null $invoice = null;

    private string $paymentMethod = 'secomm_zalopay';

    private ?string $invoiceTxnId = 'CAPTURE-19';

    private ?int $invoiceEntityId = 501;

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
        $this->logger = $this->getMockBuilder(Logger::class)->disableOriginalConstructor()->getMock();
        $this->creditmemoRepository = $this->createMock(\Magento\Sales\Api\CreditmemoRepositoryInterface::class);
        $this->creditmemoRepository->method('save')->willReturnCallback(function ($cm) {
            // Round 7 F27/F31: pin what the PERSISTED credit memo looked
            // like at the exact moment of the pre-provider save.
            $this->stateAtSave = $cm->getState();
            $this->invoiceIdAtSave = (int)$cm->getInvoiceId();
            // Emulates the resource populating entity_id on first persist.
            if ($this->creditmemoEntityId === null) {
                $this->creditmemoEntityId = 9012;
            }

            return $cm;
        });
        // Round 4 F17: the default bind succeeds (the bind-failure test
        // flips the mutable flag instead of re-configuring the mock).
        $this->mockRefundManager->method('bindCreditMemo')
            ->willReturnCallback(fn (): bool => $this->bindResult);
        // Round 5 F23: the default provider-start mark succeeds (the
        // start-failure test flips the mutable flag instead of
        // re-configuring the mock).
        $this->mockRefundManager->method('markProviderRequestStarted')
            ->willReturnCallback(fn (): bool => $this->providerStartResult);
        $this->plugin = new CreditmemoRefundPlugin(
            $this->method,
            $this->paymentDataObjectFactory,
            $this->mockRefundCommand,
            $this->mockRefundManager,
            $this->marker,
            $this->messageManager,
            $this->preflight,
            $this->creditmemoRepository,
            $this->logger
        );

        $this->payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $this->payment->method('getMethod')->willReturnCallback(fn (): string => $this->paymentMethod);
        $this->order = $this->createMock(Order::class);
        $this->order->method('getPayment')->willReturn($this->payment);
        $this->order->method('getId')->willReturn(77);
        $this->creditmemo = $this->createMock(\Magento\Sales\Model\Order\Creditmemo::class);
        $this->creditmemo->method('getOrder')->willReturn($this->order);
        $this->creditmemo->method('getBaseGrandTotal')->willReturn(25.0);
        $this->creditmemo->method('getEntityId')->willReturnCallback(fn (): ?int => $this->creditmemoEntityId);
        $this->creditmemo->method('getInvoice')->willReturnCallback(fn () => $this->invoice);
        $this->creditmemo->method('getState')->willReturnCallback(fn (): ?int => $this->stateAtSave);
        $this->creditmemo->method('setState')->willReturnCallback(function ($state) {
            $this->stateAtSave = $state;

            return $this->creditmemo;
        });
        $this->creditmemo->method('setInvoiceId')->willReturnCallback(function ($invoiceId) {
            $this->invoiceIdAtSave = (int)$invoiceId;

            return $this->creditmemo;
        });
        $this->creditmemo->method('getInvoiceId')->willReturnCallback(fn (): ?int => $this->invoiceIdAtSave);
        $this->invoice = $this->createMock(Invoice::class);
        $this->invoice->method('getTransactionId')->willReturnCallback(fn (): ?string => $this->invoiceTxnId);
        $this->invoice->method('getEntityId')->willReturnCallback(fn (): ?int => $this->invoiceEntityId);
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
     * Round 3 seams: a prepared provider request identity (stable
     * m_refund_id, NO network I/O) for the plugin's prepare/claim/
     * executePrepared flow.
     */
    private function makePreparedRequest(string $mRefundId): RefundRequest
    {
        $tracking = new RefundOutcome(RefundOutcome::STATUS_PROCESSING, $mRefundId, 25000, '{"app_id":1}');

        return new RefundRequest(['payload' => 1], $mRefundId, '{"app_id":1}', $tracking);
    }

    /**
     * @return \Secomm\ZaloPay\Model\RefundModel|\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeClaim()
    {
        return $this->createMock(\Secomm\ZaloPay\Model\RefundModel::class);
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
        $this->mockRefundCommand->expects($this->never())->method('prepare');
        $this->mockRefundCommand->expects($this->never())->method('executePrepared');
        $this->mockRefundManager->expects($this->never())->method('acquireClaim');
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
     * GATE (round 7 F31): refund without an invoice (no capture) is refused.
     */
    public function testMissingInvoiceRefused(): void
    {
        $this->invoice = null;
        $this->mockRefundCommand->expects($this->never())->method('prepare');
        $this->mockRefundCommand->expects($this->never())->method('executePrepared');
        $this->creditmemoRepository->expects($this->never())->method('save');
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('missing invoice must be refused');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('original invoice for this refund cannot be found', $exception->getMessage());
        }
        $this->assertSame([], $spy['calls']);
    }

    /**
     * GATE (round 7 F31): an invoice without a REAL entity id is refused
     * too - the credit memo must link a persisted invoice, or a
     * cron-reloaded credit memo would finalize as OFFLINE.
     */
    public function testInvoiceWithoutEntityIdRefused(): void
    {
        $this->invoiceEntityId = null;
        $this->mockRefundCommand->expects($this->never())->method('prepare');
        $this->mockRefundCommand->expects($this->never())->method('executePrepared');
        $this->mockRefundManager->expects($this->never())->method('acquireClaim');
        $this->creditmemoRepository->expects($this->never())->method('save');
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('invoice without entity id must be refused');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('original invoice for this refund cannot be found', $exception->getMessage());
        }
        $this->assertSame([], $spy['calls']);
    }

    /**
     * GATE: invoice without a transaction id is refused too.
     */
    public function testInvoiceWithoutTransactionIdRefused(): void
    {
        $this->invoiceTxnId = '';
        $this->mockRefundCommand->expects($this->never())->method('prepare');
        $this->mockRefundCommand->expects($this->never())->method('executePrepared');
        $this->creditmemoRepository->expects($this->never())->method('save');
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
     * Round 3 BLOCKER 1 lifecycle (F12): the ATOMIC durable claim is
     * acquired BETWEEN the prepared identity and the provider call; a
     * PROCESSING outcome marks the claim durable and STOPS before the core
     * refund accounting - total_refunded/qty_refunded stay untouched, the
     * order cannot become CLOSED.
     */
    public function testProcessingRegistersPendingAndStopsBeforeCore(): void
    {
        $request = $this->makePreparedRequest('260916_1000_777_r1');
        $claim = $this->makeClaim();
        $this->paymentDataObjectFactory->expects($this->once())->method('create')
            ->with($this->identicalTo($this->payment))
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->expects($this->once())->method('prepare')
            ->with($this->callback(function (array $subject): bool {
                return ($subject['amount'] ?? null) === 25.0
                    && isset($subject['payment']);
            }))
            ->willReturn($request);
        $this->mockRefundManager->expects($this->once())->method('acquireClaim')
            ->with(
                $this->identicalTo($this->creditmemo),
                $this->identicalTo($request->getTracking())
            )
            ->willReturn($claim);
        $this->mockRefundCommand->expects($this->once())->method('executePrepared')
            ->with($this->identicalTo($request))
            ->willReturn(new RefundOutcome(RefundOutcome::STATUS_PROCESSING, '260916_1000_777_r1', 25000, null));
        $this->payment->expects($this->once())->method('setCreditmemo')
            ->with($this->identicalTo($this->creditmemo));
        $this->payment->expects($this->once())->method('setParentTransactionId')->with('CAPTURE-19');
        // Round 7 F34: markProcessing carries ONLY the claim - the credit
        // memo STAYS STATE_OPEN (no custom parking state, no re-save).
        $processingArgs = null;
        $this->mockRefundManager->expects($this->once())->method('markProcessing')
            ->willReturnCallback(function (...$args) use (&$processingArgs, $claim) {
                $processingArgs = $args;

                return $claim;
            });
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
        $this->assertCount(1, $processingArgs, 'markProcessing must receive ONLY the claim (F34)');
        $this->assertSame($claim, $processingArgs[0]);
    }    /**
     * Round 7 F28: confirmed SUCCESS grants the ONE-SHOT provider-skip for
     * THIS credit memo id, the NATIVE core flow consumes it (exactly once),
     * the finally always drops the pin, and the terminal bookkeeping lands
     * on the claim (confirmed_success, slot released - F12/F13).
     */
    public function testSuccessMarksOutcomeAndRunsCoreFlow(): void
    {
        $request = $this->makePreparedRequest('260916_1000_777_r2');
        $claim = $this->makeClaim();
        $this->paymentDataObjectFactory->method('create')
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->method('prepare')->willReturn($request);
        $this->mockRefundManager->method('acquireClaim')->willReturn($claim);
        $this->mockRefundCommand->expects($this->once())->method('executePrepared')
            ->willReturn(new RefundOutcome(RefundOutcome::STATUS_SUCCESS, '260916_1000_777_r2', 25000, null));
        $this->mockRefundManager->expects($this->once())->method('markConfirmedSuccess')
            ->with($this->identicalTo($claim));

        $markerConsumedInsideCore = null;
        $spy = $this->proceedSpy('CORE-RESULT-SUCCESS');
        $coreCallable = function ($cm, $off = null) use (&$markerConsumedInsideCore, $spy) {
            // Inside the core flow, the gateway refund command consumes the
            // one-shot authorization (provider skip, exactly once).
            $markerConsumedInsideCore = $this->marker->consume((int)$cm->getEntityId());
            $spy['calls'][] = [$cm, $off];

            return 'CORE-RESULT-SUCCESS';
        };
        $result = $this->plugin->aroundRefund(
            $this->createMock(CreditmemoService::class),
            $coreCallable,
            $this->creditmemo
        );
        $this->assertSame('CORE-RESULT-SUCCESS', $result);
        $this->assertSame([[$this->creditmemo, false]], $spy['calls']);
        $this->assertTrue($markerConsumedInsideCore, 'authorization live and consumed inside the core flow');
        $this->assertFalse($this->marker->consume(9012), 'finally clear: nothing leaks past the success path');
    }
    /**
     * Round 7 F28 FAIL-CLOSED: prepare() returning null (a stray one-shot
     * provider-skip authorization for THIS credit memo) must NOT hand over
     * to the core flow - provider call 0, accounting 0, claim 0, safe
     * LocalizedException, safe log evidence.
     */
    public function testNullPrepareFailsClosed(): void
    {
        $this->mockRefundCommand->expects($this->once())->method('prepare')->willReturn(null);
        $this->mockRefundManager->expects($this->never())->method('acquireClaim');
        $this->mockRefundCommand->expects($this->never())->method('executePrepared');
        $this->creditmemoRepository->expects($this->never())->method('save');
        $this->logger->expects($this->once())->method('error')
            ->with($this->stringContains('fail-closed'));
        $spy = $this->proceedSpy('CORE-RESULT-NULL');
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('stray provider-skip authorization must fail closed');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString(
                'local tracking state is inconsistent',
                $exception->getMessage()
            );
        }
        $this->assertSame([], $spy['calls'], 'the core flow must never run on an inconsistent marker state');
    }    /**
     * Round 3 BLOCKER 1 lifecycle (F16): transport failure WITH a tracking
     * outcome lands the SEMANTIC UNKNOWN state on the durable claim
     * (explicitly NOT processing) - reconciled by m_refund_id, never
     * re-requested - and surfaces an honest, non-technical error.
     */
    public function testTransportWithOutcomeRegistersPendingAndThrows(): void
    {
        $request = $this->makePreparedRequest('260916_1000_777_r3');
        $claim = $this->makeClaim();
        $this->paymentDataObjectFactory->method('create')
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->method('prepare')->willReturn($request);
        $this->mockRefundManager->method('acquireClaim')->willReturn($claim);
        $transport = new RefundTransportException(
            new \Magento\Framework\Phrase('Zalopay: Refund failed. Please try again later.'),
            null,
            $request->getTracking()
        );
        $this->mockRefundCommand->expects($this->once())->method('executePrepared')
            ->willThrowException($transport);
        $this->mockRefundManager->expects($this->once())->method('markUnknown')
            ->with(
                $this->identicalTo($claim),
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
     * Round 3: a transport failure WITHOUT a carried outcome still happens
     * AFTER the atomic claim exists - the claim row lands UNKNOWN with
     * explicit missing-identity evidence (never left in initiating limbo).
     */
    public function testUntrackableTransportStillMarksUnknownOnClaim(): void
    {
        $request = $this->makePreparedRequest('260916_1000_777_r4');
        $claim = $this->makeClaim();
        $this->paymentDataObjectFactory->method('create')
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->method('prepare')->willReturn($request);
        $this->mockRefundManager->method('acquireClaim')->willReturn($claim);
        $transport = new RefundTransportException(
            new \Magento\Framework\Phrase('Zalopay: Refund failed. Please try again later.')
        );
        $this->mockRefundCommand->expects($this->once())->method('executePrepared')
            ->willThrowException($transport);
        $this->mockRefundManager->expects($this->once())->method('markUnknown')
            ->with(
                $this->identicalTo($claim),
                'transport_error: tracking outcome missing'
            );
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
     * Round 3 BLOCKER 1 lifecycle: a provider-CONFIRMED refusal lands
     * CONFIRMED_FAIL on the claim (releases the claim slot - the money
     * provably never left) and the ORIGINAL safe message propagates
     * untouched - the core accounting never runs.
     */
    public function testProviderFailPropagatesUntouched(): void
    {
        $request = $this->makePreparedRequest('260916_1000_777_r5');
        $claim = $this->makeClaim();
        $this->paymentDataObjectFactory->method('create')
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->method('prepare')->willReturn($request);
        $this->mockRefundManager->method('acquireClaim')->willReturn($claim);
        $refusal = new LocalizedException(new \Magento\Framework\Phrase('Zalopay: Refund failed.'));
        $this->mockRefundCommand->expects($this->once())->method('executePrepared')
            ->willThrowException($refusal);
        $this->mockRefundManager->expects($this->once())->method('markConfirmedFail')
            ->with(
                $this->identicalTo($claim),
                'refund_failed: Zalopay: Refund failed.'
            );
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
     * Round 7 F30: a provider PROTOCOL ANOMALY (return_code missing /
     * non-numeric / unexpected) is NOT a confirmed refusal: the claim lands
     * durable UNKNOWN with anomaly evidence (claim NOT released - the same
     * m_refund_id is reconciled via v2/query_refund, never re-requested
     * fresh) and the safe exception propagates untouched.
     */
    public function testProtocolAnomalyMarksUnknownAndKeepsClaim(): void
    {
        $request = $this->makePreparedRequest('260916_1000_777_f30');
        $claim = $this->makeClaim();
        $this->paymentDataObjectFactory->method('create')
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->method('prepare')->willReturn($request);
        $this->mockRefundManager->method('acquireClaim')->willReturn($claim);
        $anomaly = new RefundProtocolException(
            new \Magento\Framework\Phrase(
                'Zalopay: Refund status could not be confirmed. The refund is tracked and will be reconciled automatically.'
            )
        );
        $this->mockRefundCommand->expects($this->once())->method('executePrepared')
            ->willThrowException($anomaly);
        $this->mockRefundManager->expects($this->once())->method('markUnknown')
            ->with(
                $this->identicalTo($claim),
                $this->callback(function (string $evidence): bool {
                    return str_starts_with($evidence, PendingRefundManager::EVIDENCE_ANOMALY)
                        && str_contains($evidence, 'refund status not confirmable');
                })
            );
        // UNKNOWN must never release the claim: confirmed_fail is FORBIDDEN.
        $this->mockRefundManager->expects($this->never())->method('markConfirmedFail');
        $spy = $this->proceedSpy();
        $caught = null;
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('protocol anomaly must surface an error');
        } catch (LocalizedException $exception) {
            $caught = $exception;
        }
        $this->assertSame($anomaly, $caught, 'the safe, already-typed protocol exception propagates untouched');
        $this->assertSame([], $spy['calls']);
    }    /**
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
        $this->mockRefundCommand->expects($this->never())->method('prepare');
        $this->mockRefundCommand->expects($this->never())->method('executePrepared');
        $this->mockRefundManager->expects($this->never())->method('acquireClaim');
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
        $this->mockRefundCommand->expects($this->never())->method('prepare');
        $this->mockRefundCommand->expects($this->never())->method('executePrepared');
        $this->mockRefundManager->expects($this->never())->method('acquireClaim');
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
        $this->mockRefundCommand->expects($this->never())->method('prepare');
        $this->mockRefundCommand->expects($this->never())->method('executePrepared');
        $this->mockRefundManager->expects($this->never())->method('acquireClaim');
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
        $this->mockRefundCommand->expects($this->never())->method('prepare');
        $this->mockRefundCommand->expects($this->never())->method('executePrepared');
        $this->mockRefundManager->expects($this->never())->method('acquireClaim');
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
     * Round 3 F12: the LOSER of the atomic claim race NEVER reaches the
     * provider - the duplicate-claim exception propagates untouched, the
     * core flow never runs.
     */
    public function testClaimConflictPropagatesWithoutProviderCall(): void
    {
        $request = $this->makePreparedRequest('260916_1000_777_r6');
        $this->paymentDataObjectFactory->method('create')
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->method('prepare')->willReturn($request);
        $this->mockRefundManager->expects($this->once())->method('acquireClaim')
            ->willThrowException(new LocalizedException(
                new \Magento\Framework\Phrase(
                    'Zalopay: Another refund for this order is active or awaiting reconciliation.'
                )
            ));
        $this->mockRefundCommand->expects($this->never())->method('executePrepared');
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('claim conflict must propagate');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('active or awaiting reconciliation', $exception->getMessage());
        }
        $this->assertSame([], $spy['calls']);
    }

    /**
     * Round 3 BLOCKER 2 (F13): provider SUCCESS + core-finalize failure =
     * durable PROVIDER_SUCCESS_LOCAL_PENDING - the provider was asked
     * EXACTLY once and is never re-asked; the cron finalizes locally only.
     */
    public function testProviderSuccessWithLocalFailureLandsPendingState(): void
    {
        $request = $this->makePreparedRequest('260916_1000_777_r7');
        $claim = $this->makeClaim();
        $this->paymentDataObjectFactory->method('create')
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->method('prepare')->willReturn($request);
        $this->mockRefundManager->method('acquireClaim')->willReturn($claim);
        $this->mockRefundCommand->expects($this->once())->method('executePrepared')
            ->willReturn(new RefundOutcome(RefundOutcome::STATUS_SUCCESS, '260916_1000_777_r7', 25000, null));
        $this->mockRefundManager->expects($this->never())->method('markConfirmedSuccess');
        $this->mockRefundManager->expects($this->once())->method('markProviderSuccessLocalPending')
            ->with(
                $this->identicalTo($claim),
                $this->stringStartsWith('reconcile_error: core finalize failed:')
            );
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                function () {
                    throw new \RuntimeException('accounting boom');
                },
                $this->creditmemo
            );
            self::fail('local finalize failure must surface an error');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString(
                'succeeded at the provider but the local accounting is incomplete',
                $exception->getMessage()
            );
        }
        $this->assertSame([], $spy['calls']);
        // Round 7 F28: the finally dropped the pin - the failed finalize
        // can never leak the provider-skip into any later refund.
        $this->assertFalse($this->marker->consume(9012));
        $this->assertFalse($this->marker->consume(77));
    }

    /**
     * Round 7 F27/F31 REQUIRED TEST: the pre-provider persistence pins the
     * credit memo to the NATIVE STATE_OPEN with its invoice_id bound, and
     * the save happens BEFORE the provider I/O (F27) - so the synchronous
     * SUCCESS path can complete the NATIVE core flow (core
     * validateForRefund accepts an EXISTING credit memo only in OPEN).
     */
    public function testPreProviderPersistenceSetsOpenStateAndInvoiceIdBeforeProvider(): void
    {
        $request = $this->makePreparedRequest('260916_1000_777_f27');
        $claim = $this->makeClaim();
        self::assertNull($this->creditmemoEntityId, 'the REAL admin flow presents an UNSAVED credit memo');
        $this->paymentDataObjectFactory->method('create')
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->method('prepare')->willReturn($request);
        $this->mockRefundManager->method('acquireClaim')->willReturn($claim);
        $order = [];
        $this->creditmemoRepository->expects($this->once())->method('save')
            ->willReturnCallback(function ($cm) use (&$order) {
                $order[] = 'save';
                $this->creditmemoEntityId = 9012;

                return $cm;
            });
        $this->mockRefundManager->method('bindCreditMemo')->willReturnCallback(function () use (&$order) {
            $order[] = 'bind';

            return true;
        });
        $this->mockRefundManager->method('markProviderRequestStarted')->willReturnCallback(function () use (&$order) {
            $order[] = 'start';

            return true;
        });
        $this->mockRefundCommand->expects($this->once())->method('executePrepared')
            ->willReturnCallback(function () use (&$order) {
                $order[] = 'provider';

                return new RefundOutcome(RefundOutcome::STATUS_SUCCESS, '260916_1000_777_f27', 25000, null);
            });
        $this->plugin->aroundRefund(
            $this->createMock(CreditmemoService::class),
            $this->proceedSpy('CORE-RESULT-F27')['callable'],
            $this->creditmemo
        );
        // (a) save called with CM state OPEN + invoice_id set ...
        $this->assertSame(
            \Magento\Sales\Model\Order\Creditmemo::STATE_OPEN,
            $this->stateAtSave,
            'F27: the persisted credit memo must be STATE_OPEN before the provider is asked'
        );
        $this->assertSame(501, $this->invoiceIdAtSave, 'F31: invoice_id must be pinned before the save');
        // (b) ... and the save happens BEFORE executePrepared.
        $this->assertSame(['save', 'bind', 'start', 'provider'], $order);
    }

    /**
     * Round 7 F27 REQUIRED TEST: the pre-provider persistence touches ONLY
     * the credit memo repository - orderRepository/invoiceRepository are
     * never involved before (or after) the provider I/O: no refund
     * accounting mutation can come from this plugin (order.total_refunded,
     * invoice.is_used_for_refund and friends are exclusively the native
     * core flow's / the cron finalize's business).
     */
    public function testPreProviderPersistenceTouchesOnlyCreditmemoRepository(): void
    {
        $reflection = new \ReflectionClass(CreditmemoRefundPlugin::class);
        $repositoryDeps = [];
        foreach ($reflection->getConstructor()->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof \ReflectionNamedType) {
                continue;
            }
            $typeName = $type->getName();
            if (is_a($typeName, \Magento\Sales\Api\OrderRepositoryInterface::class, true)
                || is_a($typeName, \Magento\Sales\Api\InvoiceRepositoryInterface::class, true)
                || is_a($typeName, \Magento\Sales\Api\OrderPaymentRepositoryInterface::class, true)
            ) {
                $repositoryDeps[] = $typeName;
            }
        }

        $this->assertSame(
            [],
            $repositoryDeps,
            'F27: the plugin must not carry any order/invoice/payment repository dependency'
        );
    }

    /**
     * Round 3 ROBUSTNESS: an unresolvable order surfaces the
     * Magento-compatible NoSuchEntityException BEFORE preflight/claim/
     * provider - provider call 0, persistence 0.
     */
    public function testInvalidOrderSurfacesNoSuchEntityBeforeAnything(): void
    {
        $broken = $this->createMock(\Magento\Sales\Model\Order\Creditmemo::class);
        $broken->method('getOrder')->willThrowException(new \RuntimeException('orphan entity'));
        $this->preflight->expects($this->never())->method('validateRefundable');
        $this->mockRefundCommand->expects($this->never())->method('prepare');
        $this->mockRefundManager->expects($this->never())->method('acquireClaim');
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $broken
            );
            self::fail('invalid order must surface the Magento-compatible exception');
        } catch (NoSuchEntityException $exception) {
            $this->assertStringContainsString('invalid order', $exception->getMessage());
        }
        $this->assertSame([], $spy['calls']);
    }

    /**
     * Round 3 ordering guarantee: preflight (Magento validation) ->
     * prepare (identity, no I/O) -> acquireClaim (durable claim) ->
     * executePrepared (the ONE provider call) - validation and the claim
     * both happen BEFORE any provider I/O.
     */
    public function testValidRefundValidatedBeforeProviderOnce(): void
    {
        $order = [];
        $request = $this->makePreparedRequest('260916_1000_777_r9');
        $claim = $this->makeClaim();
        $this->preflight->expects($this->once())->method('validateRefundable')
            ->willReturnCallback(function () use (&$order) {
                $order[] = 'preflight';

                return null;
            });
        $this->paymentDataObjectFactory->method('create')
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->method('prepare')
            ->willReturnCallback(function () use (&$order, $request) {
                $order[] = 'prepare';

                return $request;
            });
        $this->mockRefundManager->method('acquireClaim')
            ->willReturnCallback(function () use (&$order, $claim) {
                $order[] = 'claim';

                return $claim;
            });
        $this->mockRefundCommand->method('executePrepared')
            ->willReturnCallback(function () use (&$order) {
                $order[] = 'provider';

                return new RefundOutcome(RefundOutcome::STATUS_PROCESSING, '260916_1000_777_r9', 25000, null);
            });
        $this->mockRefundManager->method('markProcessing');
        $this->plugin->aroundRefund(
            $this->createMock(CreditmemoService::class),
            $this->proceedSpy()['callable'],
            $this->creditmemo
        );
        $this->assertSame(['preflight', 'prepare', 'claim', 'provider'], $order);
    }
    /**
     * Round 4 F17 REQUIRED TEST: a credit memo representative of the REAL
     * admin online refund - entity_id is NULL when the claim is acquired
     * (CreditmemoFactory::createByInvoice/createByOrder semantics; the test
     * does NOT pre-stub any entity id). Prove the full gate: atomic claim
     * succeeds -> the credit memo is persisted and obtains a REAL id -> the
     * claim binds that REAL id -> the provider is called EXACTLY once.
     */
    public function testRealAdminUnsavedCreditmemoBindBeforeProvider(): void
    {
        $request = $this->makePreparedRequest('260916_1000_777_r10');
        $claim = $this->makeClaim();
        // The REAL admin flow: NO pre-stubbed entity id at entry.
        self::assertNull($this->creditmemoEntityId);
        $this->paymentDataObjectFactory->method('create')
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->method('prepare')->willReturn($request);
        $this->mockRefundManager->method('acquireClaim')->willReturn($claim);
        $order = [];
        // 1. Persist: the repository save assigns the REAL entity_id.
        $this->creditmemoRepository->expects($this->once())->method('save')
            ->willReturnCallback(function ($cm) use (&$order) {
                $order[] = 'save';
                $this->creditmemoEntityId = 9012;

                return $cm;
            });
        // 2. Bind: the REAL id (proves save ran first - 9012 only exists
        //    after save) is bound to the claimed row before provider I/O.
        $this->mockRefundManager->expects($this->once())->method('bindCreditMemo')
            ->with($this->identicalTo($claim), 9012)
            ->willReturnCallback(function () use (&$order) {
                $order[] = 'bind';

                return true;
            });
        // 3. START (round 5 F23): the provider-start boundary is persisted
        //    after the bind and BEFORE any provider I/O.
        $this->mockRefundManager->expects($this->once())->method('markProviderRequestStarted')
            ->willReturnCallback(function () use (&$order) {
                $order[] = 'start';

                return true;
            });
        // 4. Provider: EXACTLY once, only after the bind + start.
        $this->mockRefundCommand->expects($this->once())->method('executePrepared')
            ->willReturnCallback(function () use (&$order) {
                $order[] = 'provider';

                return new RefundOutcome(RefundOutcome::STATUS_PROCESSING, '260916_1000_777_r10', 25000, null);
            });
        $this->plugin->aroundRefund(
            $this->createMock(CreditmemoService::class),
            $this->proceedSpy()['callable'],
            $this->creditmemo
        );
        $this->assertSame(['save', 'bind', 'start', 'provider'], $order);
        self::assertSame(9012, $this->creditmemo->getEntityId());
    }

    /**
     * Round 5 F23: the provider-start mark failing (claim slot lost between
     * bind and start) forbids provider I/O BY CONSTRUCTION - the claim is
     * terminated as abandoned-before-provider-I/O and the operator is asked
     * to retry. The provider is never asked.
     */
    public function testProviderStartMarkFailureNeverTouchesProvider(): void
    {
        $request = $this->makePreparedRequest('260916_1000_777_r13');
        $claim = $this->makeClaim();
        $this->paymentDataObjectFactory->method('create')
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->method('prepare')->willReturn($request);
        $this->mockRefundManager->method('acquireClaim')->willReturn($claim);
        $this->providerStartResult = false;
        $this->mockRefundManager->expects($this->once())->method('terminate')
            ->with(
                $this->identicalTo($claim),
                $this->callback(function (string $evidence): bool {
                    return str_starts_with($evidence, PendingRefundManager::EVIDENCE_ABANDONED)
                        && str_contains($evidence, 'provider-start state could not be persisted');
                }),
                $this->identicalTo(\Secomm\ZaloPay\Api\Data\RefundInterface::REFUND_STATE_CONFIRMED_FAIL)
            );
        $this->mockRefundCommand->expects($this->never())->method('executePrepared');
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('provider-start failure must abort the refund');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('could not be started', $exception->getMessage());
        }
        $this->assertSame([], $spy['calls']);
    }

    /**
     * Round 4 F17: a local creditmemo persist failure can NEVER reach the
     * provider - the claim is terminated as abandoned-before-provider-I/O
     * (confirmed_fail, claim released) and the operator is asked to retry.
     */
    public function testLocalPersistFailureAbandonsClaimBeforeProvider(): void
    {
        $request = $this->makePreparedRequest('260916_1000_777_r11');
        $claim = $this->makeClaim();
        $this->paymentDataObjectFactory->method('create')
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->method('prepare')->willReturn($request);
        $this->mockRefundManager->method('acquireClaim')->willReturn($claim);
        $this->creditmemoRepository->expects($this->once())->method('save')
            ->willThrowException(new \RuntimeException('write failed'));
        $this->mockRefundManager->expects($this->once())->method('terminate')
            ->with(
                $this->identicalTo($claim),
                $this->callback(function (string $evidence): bool {
                    return str_starts_with($evidence, PendingRefundManager::EVIDENCE_ABANDONED)
                        && str_contains($evidence, 'local creditmemo persist failed');
                }),
                $this->identicalTo(\Secomm\ZaloPay\Api\Data\RefundInterface::REFUND_STATE_CONFIRMED_FAIL)
            );
        $this->mockRefundCommand->expects($this->never())->method('executePrepared');
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('persist failure must abort the refund');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('could not be recorded locally', $exception->getMessage());
        }
        $this->assertSame([], $spy['calls']);
    }

    /**
     * Round 4 F17: a claim lost between acquire and bind (concurrent
     * stale-claim release / terminal transition) forbids provider I/O by
     * construction - terminate abandoned-before-provider-I/O, never ask
     * the provider.
     */
    public function testLostClaimBeforeBindNeverTouchesProvider(): void
    {
        $request = $this->makePreparedRequest('260916_1000_777_r12');
        $claim = $this->makeClaim();
        $this->paymentDataObjectFactory->method('create')
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->method('prepare')->willReturn($request);
        $this->mockRefundManager->method('acquireClaim')->willReturn($claim);
        // The bind FAILS (claim slot lost): flip the mutable flag.
        $this->bindResult = false;
        $this->mockRefundManager->expects($this->once())->method('terminate')
            ->with(
                $this->identicalTo($claim),
                $this->callback(function (string $evidence): bool {
                    return str_starts_with($evidence, PendingRefundManager::EVIDENCE_ABANDONED)
                        && str_contains($evidence, 'claim lost before bind');
                }),
                $this->identicalTo(\Secomm\ZaloPay\Api\Data\RefundInterface::REFUND_STATE_CONFIRMED_FAIL)
            );
        $this->mockRefundCommand->expects($this->never())->method('executePrepared');
        $spy = $this->proceedSpy();
        try {
            $this->plugin->aroundRefund(
                $this->createMock(CreditmemoService::class),
                $spy['callable'],
                $this->creditmemo
            );
            self::fail('lost claim must abort the refund');
        } catch (LocalizedException $exception) {
            $this->assertStringContainsString('could not be bound locally', $exception->getMessage());
        }
        $this->assertSame([], $spy['calls']);
    }

    /**
     * Round 4 F17: a PRE-SAVED credit memo (entity_id already present) is
     * reused as-is - no re-save, the existing id is bound, provider runs.
     */
    public function testPreSavedCreditmemoReusedWithoutResave(): void
    {
        $request = $this->makePreparedRequest('260916_1000_777_r13');
        $claim = $this->makeClaim();
        $this->creditmemoEntityId = 9033;
        $this->paymentDataObjectFactory->method('create')
            ->willReturn($this->createMock(PaymentDataObjectInterface::class));
        $this->mockRefundCommand->method('prepare')->willReturn($request);
        $this->mockRefundManager->method('acquireClaim')->willReturn($claim);
        $this->creditmemoRepository->expects($this->never())->method('save');
        $this->mockRefundManager->expects($this->once())->method('bindCreditMemo')
            ->with($this->identicalTo($claim), 9033)
            ->willReturn(true);
        $this->mockRefundCommand->expects($this->once())->method('executePrepared')
            ->willReturn(new RefundOutcome(RefundOutcome::STATUS_PROCESSING, '260916_1000_127_r13', 25000, null));
        $spy = $this->proceedSpy();
        $result = $this->plugin->aroundRefund(
            $this->createMock(CreditmemoService::class),
            $spy['callable'],
            $this->creditmemo
        );
        $this->assertSame($this->creditmemo, $result);
    }
}
