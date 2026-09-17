<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Service;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\ZaloPay\Api\Data\PaymentAttemptInterface;
use Secomm\ZaloPay\Api\PaymentAttemptRepositoryInterface;
use Secomm\ZaloPay\Exception\ContractMismatchException;
use Secomm\ZaloPay\Gateway\Helper\Rate;
use Secomm\ZaloPay\Model\PaymentAttempt;
use Secomm\ZaloPay\Model\QuoteContractFingerprint;
use Secomm\ZaloPay\Service\OrderFinalizer;
use Secomm\ZaloPay\Service\OrderPlacementAuthorization;
use Secomm\ZaloPay\Service\PaymentAttemptLifecycle;
use Secomm\ZaloPay\Test\Unit\Model\QuoteStub;

/**
 * ZALOPAY-PAYMENT-FIRST review fixes (TASK-EDS9T5):
 *
 * BLOCKER 1 — the CURRENT quote must still match the paid payment contract
 * (method, amount, fingerprint) before any automatic placeOrder; mismatch
 * keeps the attempt PAID (money is real), records last_error and creates NO
 * order. A duplicate FINALIZED call recovers the bound order (binding
 * validated); the customer success session is NOT a finalizer concern —
 * SuccessSessionPreparer on the Return path owns it (corrective Blocker 4),
 * so this suite proves session-free finalization. Plus the idempotent
 * place + bind + capture contract and the capture-failure rollback
 * behaviour.
 *
 * TASK-EDS9T5 placeOrder authorization (the guard contract): the internal
 * placement opens the single-use OrderPlacementAuthorization grant bound to
 * the exact (quote_id, attempt entity_id, app_trans_id) and clears it in a
 * finally block — verification (PAID status) alone never authorizes an
 * order; recovery paths and pre-grant mismatch refusals never open one.
 *
 * Corrective round 3 (Blockers 2 + 5):
 *  - a quarantined attempt (requires_reconciliation) is NEVER auto-finalized:
 *    the gate refuses before any state inspection, placeOrder or grant;
 *  - a refused finalization persists its evidence through
 *    PaymentAttemptLifecycle::recordContractMismatch() (fresh row lock) and
 *    NEVER saves its possibly-stale pre-rollback attempt copy.
 */
class OrderFinalizerTest extends TestCase
{
    private const PERSISTED_HASH = '5f4dcc3b5aa765d61d8327deb882cf99';

    /**
     * @var PaymentAttemptRepositoryInterface|MockObject
     */
    private $repository;

    /**
     * @var PaymentAttemptLifecycle|MockObject
     */
    private $lifecycle;

    /**
     * @var CartManagementInterface|MockObject
     */
    private $cartManagement;

    /**
     * @var CartRepositoryInterface|MockObject
     */
    private $cartRepository;

    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder|MockObject
     */
    private $searchCriteriaBuilder;

    /**
     * @var ConfigInterface|MockObject
     */
    private $config;

    /**
     * @var MethodInterface|MockObject
     */
    private $method;

    /**
     * @var Rate|MockObject
     */
    private $rate;

    /**
     * @var QuoteContractFingerprint|MockObject
     */
    private $fingerprint;

    /**
     * Mutable test state: whether the next claimEmailDispatch call grants
     * the dispatch claim (PHPUnit stubs on the same method do not override
     * each other, so tests flip this flag instead).
     *
     * @var bool
     */
    private $emailClaimGranted = true;

    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var OrderPlacementAuthorization|MockObject
     */
    private $placementAuthorization;

    /**
     * @var OrderFinalizer
     */
    private $finalizer;

    /**
     * @var PaymentAttempt|null
     */
    private ?PaymentAttempt $saved = null;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender|MockObject
     */
    private $orderSender;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime|MockObject
     */
    private $dateTime;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $this->cartManagement = $this->createMock(CartManagementInterface::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn(
            $this->createMock(\Magento\Framework\Api\SearchCriteriaInterface::class)
        );
        $this->config = $this->createMock(ConfigInterface::class);
        $this->config->method('getValue')->willReturn(MethodInterface::ACTION_AUTHORIZE_CAPTURE);
        $this->method = $this->createMock(MethodInterface::class);
        $this->method->method('getCode')->willReturn('zalopay');
        $this->rate = $this->createMock(Rate::class);
        $this->fingerprint = $this->createMock(QuoteContractFingerprint::class);

        $this->connection = $this->createMock(AdapterInterface::class);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->placementAuthorization = $this->createMock(OrderPlacementAuthorization::class);
        $this->lifecycle = $this->createMock(PaymentAttemptLifecycle::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->orderSender = $this->createMock(\Magento\Sales\Model\Order\Email\Sender\OrderSender::class);
        $this->dateTime = $this->createMock(\Magento\Framework\Stdlib\DateTime\DateTime::class);
        $this->dateTime->method('timestamp')->willReturn(1690000000);
        // Default: THIS finalizer wins the email dispatch claim (the
        // claim-granting conditional UPDATE lives in PaymentAttemptResource,
        // proven separately by PaymentAttemptResourceTest).
        $this->repository->method('claimEmailDispatch')->willReturnCallback(
            function (): bool {
                return $this->emailClaimGranted;
            }
        );

                $this->finalizer = new OrderFinalizer(
            $this->repository,
            $this->cartManagement,
            $this->cartRepository,
            $this->orderRepository,
            $this->searchCriteriaBuilder,
            $this->config,
            $this->method,
            $this->rate,
            $this->fingerprint,
            $resourceConnection,
            $this->placementAuthorization,
            $this->lifecycle,
            $this->logger,
            $this->orderSender,
            $this->dateTime
        );
    }

    // ---- BLOCKER 1: quote-contract validation before automatic placement ----

    /**
     * Review case 1: unchanged quote -> place, bind, capture (happy path).
     *
     * @return void
     */
    public function testFinalizePlacesBindsAndCapturesTheOrder(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubMatchingQuote();

        // Matrix 17: the placement authorization is bound to the EXACT
        // (quote_id, attempt entity_id, app_trans_id) around placeOrder.
        $this->placementAuthorization->expects($this->once())->method('grant')->with(42, 9, '260826_1000_000000123');
        $this->placementAuthorization->expects($this->once())->method('clear');

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $payment->expects($this->once())->method('setTransactionId')->with('240801000001');
        $payment->expects($this->once())->method('capture');
        $payment->method('prependMessage');

        $order = $this->newOrder(88, Order::STATE_PENDING_PAYMENT, '000000123', $payment);
        $this->cartManagement->expects($this->once())->method('placeOrder')->with(42)->willReturn(88);
        $this->orderRepository->method('get')->with(88)->willReturn($order);

        $result = $this->finalizer->finalizeOrRecover($attempt, '240801000001');

        $this->assertSame($order, $result);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $this->saved->getPaymentStatus());
        $this->assertSame(88, $this->saved->getOrderId());
        $this->assertSame('240801000001', $this->saved->getProviderTransactionId());
    }

    /**
     * Review case 2: quote total changed since payment -> NO order, attempt
     * stays PAID with the reason in last_error.
     *
     * @return void
     */
    public function testQuoteTotalChangedRefusesPlacement(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubMatchingQuote(currentVndAmount: 700000);

        // Round-3 Blocker 2: the refusal evidence goes through the LIFECYCLE
        // (fresh row lock) — the finalizer NEVER saves its possibly-stale
        // pre-rollback copy.
        $this->repository->expects($this->never())->method('save');
        $this->lifecycle->expects($this->once())->method('recordContractMismatch')
            ->with('260826_1000_000000123', $this->stringContains('total changed'));

        // Matrix 19: a refused contract never opens the placement grant —
        // PAID verification alone authorizes nothing.
        $this->placementAuthorization->expects($this->never())->method('grant');
        $this->placementAuthorization->expects($this->never())->method('clear');
        $this->cartManagement->expects($this->never())->method('placeOrder');

        try {
            $this->finalizer->finalizeOrRecover($attempt, '240801000001');
            $this->fail('ContractMismatchException was not thrown.');
        } catch (ContractMismatchException $e) {
            $this->assertStringContainsString('total changed', $e->getMessage());
        }
        // The in-memory attempt copy keeps its money-real state (it was never
        // persisted after the rollback — nothing regressed).
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
        $this->assertNull($attempt->getOrderId());
    }

    /**
     * Review cases 3/4/5: qty/items/shipping changed while the total stayed
     * the same -> fingerprint mismatch -> NO order, stays PAID.
     *
     * @return void
     */
    public function testContractFingerprintMismatchRefusesPlacement(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubMatchingQuote(hashMatches: false);
        $this->repository->expects($this->never())->method('save');
        $this->lifecycle->expects($this->once())->method('recordContractMismatch')
            ->with('260826_1000_000000123', $this->stringContains('fingerprint mismatch'));

        $this->cartManagement->expects($this->never())->method('placeOrder');

        try {
            $this->finalizer->finalizeOrRecover($attempt, '240801000001');
            $this->fail('ContractMismatchException was not thrown.');
        } catch (ContractMismatchException $e) {
            $this->assertStringContainsString('fingerprint mismatch', $e->getMessage());
        }
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
    }

    /**
     * Review case 6: payment method switched away from ZaloPay -> NO order.
     *
     * @return void
     */
    public function testQuotePaymentMethodChangedRefusesPlacement(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubMatchingQuote(quoteMethod: 'checkmo');

        $this->cartManagement->expects($this->never())->method('placeOrder');

        $this->expectException(ContractMismatchException::class);
        $this->expectExceptionMessage('payment method changed');
        $this->finalizer->finalizeOrRecover($attempt, '240801000001');
    }

    /**
     * Review case 7: quote inactive and no matching order -> safe recovery
     * state: stays PAID, last_error recorded, no order created.
     *
     * @return void
     */
    public function testInactiveQuoteWithoutOrderKeepsPaidAttemptForReconciliation(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubMatchingQuote(active: false);
        $this->stubOrderSearch([]);
        $this->repository->expects($this->never())->method('save');
        $this->lifecycle->expects($this->once())->method('recordContractMismatch')
            ->with('260826_1000_000000123', $this->stringContains('inactive'));

        $this->cartManagement->expects($this->never())->method('placeOrder');

        try {
            $this->finalizer->finalizeOrRecover($attempt, '240801000001');
            $this->fail('ContractMismatchException was not thrown.');
        } catch (ContractMismatchException $e) {
            $this->assertStringContainsString('inactive', $e->getMessage());
        }
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $attempt->getPaymentStatus());
        $this->assertNull($attempt->getOrderId());
    }

    /**
     * Review case 8 (variant A): quote already submitted (inactive) but the
     * order exists under the reserved increment id -> bind that order.
     *
     * @return void
     */
    public function testInactiveQuoteWithMatchingOrderBindsExistingOrder(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubMatchingQuote(active: false);

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $existing = $this->newOrder(99, Order::STATE_PROCESSING, '000000123', $payment);
        $this->stubOrderSearch([$existing]);

        $result = $this->finalizer->finalizeOrRecover($attempt, '240801000001');

        $this->assertSame($existing, $result);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $this->saved->getPaymentStatus());
        $this->assertSame(99, $this->saved->getOrderId());
    }

    /**
     * Case 8 (variant B): the quote validated ACTIVE but was submitted
     * between validation and placeOrder (NoSuchEntity) -> recover the
     * existing order, never a second one.
     *
     * @return void
     */
    public function testConcurrentPlacementIsRecoveredByReservedOrderId(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubMatchingQuote();

        $this->cartManagement->method('placeOrder')
            ->willThrowException(new NoSuchEntityException(__('No such entity.')));

        // Matrix 18: the grant is single-use and cleared in the finally
        // block even when placeOrder explodes mid-placement.
        $this->placementAuthorization->expects($this->once())->method('grant')->with(42, 9, '260826_1000_000000123');
        $this->placementAuthorization->expects($this->once())->method('clear');

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $payment->method('prependMessage');
        $existing = $this->newOrder(99, Order::STATE_PROCESSING, '000000123', $payment);
        $existing->method('addCommentToStatusHistory')->willReturnSelf();
        $this->stubOrderSearch([$existing]);

        $result = $this->finalizer->finalizeOrRecover($attempt, '240801000001');

        $this->assertSame($existing, $result);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $this->saved->getPaymentStatus());
        $this->assertSame(99, $this->saved->getOrderId());
        // No PENDING_PAYMENT capture path for the already-processing order.
        $payment->expects($this->never())->method('capture');
    }

    // ---- FINALIZED duplicate recovery (customer session is NOT finalizer scope) ----

    /**
     * Review case 1/5: duplicate return on FINALIZED -> existing order, no
     * second placement, binding validated.
     *
     * @return void
     */
    public function testDuplicateFinalizeReturnsExistingOrderWithoutPlacing(): void
    {
        $attempt = $this->newPaidAttempt()->markFinalized(77);
        $this->repository->method('lockByAppTransId')->willReturn($attempt);

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $existing = $this->newOrder(77, Order::STATE_PROCESSING, '000000123', $payment);
        $this->orderRepository->method('get')->with(77)->willReturn($existing);
        $this->cartManagement->expects($this->never())->method('placeOrder');
        // Matrix 20: FINALIZED recovery never opens a placement grant.
        $this->placementAuthorization->expects($this->never())->method('grant');

        $order = $this->finalizer->finalizeOrRecover($attempt, '240801000001');

        $this->assertSame($existing, $order);
        $this->assertSame(77, $attempt->getOrderId());
    }

    /**
     * Corrective Blocker 4: the finalizer is session-free — a FINALIZED
     * duplicate recovery writes NO checkout session state (the Return
     * processor's SuccessSessionPreparer owns that).
     *
     * @return void
     */
    public function testDuplicateFinalizeDoesNotTouchCheckoutSession(): void
    {
        $reflector = new \ReflectionClass(OrderFinalizer::class);
        $sessionParams = array_filter(
            $reflector->getConstructor()->getParameters(),
            fn (\ReflectionParameter $parameter) => $parameter->getType()?->getName() === \Magento\Checkout\Model\Session::class
        );

        $this->assertSame(
            [],
            $sessionParams,
            'OrderFinalizer must not depend on the checkout session (SuccessSessionPreparer owns it).'
        );
    }

    /**
     * Review case 3: FINALIZED without a bound order -> reconciliation,
     * customer-safe failure.
     *
     * @return void
     */
    public function testFinalizedWithoutBoundOrderIsRefused(): void
    {
        $attempt = $this->newPaidAttempt();
        $attempt->setPaymentStatus(PaymentAttemptInterface::STATUS_FINALIZED); // no order bound
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->repository->expects($this->never())->method('save');
        $this->lifecycle->expects($this->once())->method('recordContractMismatch')
            ->with('260826_1000_000000123', $this->stringContains('without a bound order'));

        $this->expectException(ContractMismatchException::class);
        $this->expectExceptionMessage('without a bound order');
        $this->finalizer->finalizeOrRecover($attempt);
    }

    /**
     * Review case 4: bound order does not match the attempt contract
     * (different increment id) -> rejected.
     *
     * @return void
     */
    public function testFinalizedWithWrongOrderBindingIsRefused(): void
    {
        $attempt = $this->newPaidAttempt()->markFinalized(77);
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->repository->expects($this->never())->method('save');
        $this->lifecycle->expects($this->once())->method('recordContractMismatch')
            ->with('260826_1000_000000123', $this->stringContains('does not match'));

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $foreign = $this->newOrder(77, Order::STATE_PROCESSING, '000000999', $payment); // not our reserved id
        $this->orderRepository->method('get')->with(77)->willReturn($foreign);

        $this->expectException(ContractMismatchException::class);
        $this->expectExceptionMessage('does not match');
        $this->finalizer->finalizeOrRecover($attempt);
    }

    // ---- Round 3, Blocker 5: the quarantine gate ----

    /**
     * Round-3 case 18: a quarantined attempt (requires_reconciliation — e.g.
     * an amount mismatch recorded earlier) is NEVER auto-finalized by a
     * later generic callback: the gate refuses BEFORE any state inspection,
     * placement, grant or capture.
     *
     * @return void
     */
    public function testQuarantinedAttemptIsNeverAutoFinalized(): void
    {
        $attempt = $this->newPaidAttempt();
        $attempt->setRequiresReconciliation(true);
        $attempt->setReconciliationCode(PaymentAttemptInterface::RECON_AMOUNT_MISMATCH);
        $this->repository->method('lockByAppTransId')->willReturn($attempt);

        $this->cartManagement->expects($this->never())->method('placeOrder');
        $this->placementAuthorization->expects($this->never())->method('grant');
        $this->repository->expects($this->never())->method('save');
        // The refusal is still quarantined-evidenced through the lifecycle.
        $this->lifecycle->expects($this->once())->method('recordContractMismatch')
            ->with('260826_1000_000000123', $this->stringContains('requires reconciliation'));

        $this->expectException(ContractMismatchException::class);
        $this->expectExceptionMessage('requires reconciliation (amount_mismatch)');
        $this->finalizer->finalizeOrRecover($attempt, '240801000001');
    }

    /**
     * The quarantine gate takes precedence even over a FINALIZED row
     * (defense in depth — the lifecycle never quarantines a bound order,
     * but if a row ever lands in that state it is refused, never silently
     * recovered).
     *
     * @return void
     */
    public function testQuarantinedFinalizedRowIsRefusedToo(): void
    {
        $attempt = $this->newPaidAttempt()->markFinalized(77);
        $attempt->setRequiresReconciliation(true);
        $attempt->setReconciliationCode(PaymentAttemptInterface::RECON_PROVIDER_TX_CONFLICT);
        $this->repository->method('lockByAppTransId')->willReturn($attempt);

        $this->orderRepository->expects($this->never())->method('get');
        $this->lifecycle->expects($this->once())->method('recordContractMismatch');

        $this->expectException(ContractMismatchException::class);
        $this->expectExceptionMessage('requires reconciliation');
        $this->finalizer->finalizeOrRecover($attempt, '240801000001');
    }

    // ---- Lifecycle + transaction behaviour ----

    /**
     * A terminal-but-not-paid attempt cannot be finalized — explicit error,
     * nothing placed.
     *
     * @return void
     */
    public function testNonPayableAttemptRefusesFinalization(): void
    {
        $attempt = $this->newActiveAttempt();
        $attempt->markStale(); // ACTIVE -> STALE (superseded): legal terminal state.
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->cartManagement->expects($this->never())->method('placeOrder');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('cannot be finalized');
        $this->finalizer->finalizeOrRecover($attempt);
    }

    /**
     * Capture fails (order save error): the whole unit rolls back — no
     * half-committed FINALIZED state, no success session.
     *
     * @return void
     */
    public function testCaptureFailureRollsBackTransaction(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubMatchingQuote();

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $payment->method('capture')->willThrowException(new LocalizedException(__('Capture failed.')));
        $order = $this->newOrder(88, Order::STATE_PENDING_PAYMENT, '000000123', $payment);
        $this->cartManagement->method('placeOrder')->willReturn(88);
        $this->orderRepository->method('get')->with(88)->willReturn($order);

        $this->connection->expects($this->once())->method('rollBack');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Capture failed');
        $this->finalizer->finalizeOrRecover($attempt, '240801000001');
    }

    // ---- TASK-CG6BM7: email idempotency + post-commit semantics (EMAIL 1-7) ----

    /**
     * EMAIL 1: a fresh finalize sends the confirmation exactly once, AFTER
     * the DB transaction commits (send happens on the success path only —
     * capture/contract failures never reach it).
     */
    public function testFreshFinalizeSendsConfirmationEmailOnce(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubMatchingQuote();

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $payment->method('capture');
        $payment->method('prependMessage');
        $order = $this->newOrder(88, Order::STATE_PENDING_PAYMENT, '000000123', $payment);
        $this->cartManagement->method('placeOrder')->willReturn(88);
        $this->orderRepository->method('get')->with(88)->willReturn($order);

        $this->connection->expects($this->once())->method('commit');
        $this->orderSender->expects($this->once())->method('send')->with($order);

        $this->finalizer->finalizeOrRecover($attempt, '240801000001');
    }

    /**
     * EMAIL 2/6: a FINALIZED duplicate (Return revisit, duplicate IPN or
     * recovery) on an already-emailed order NEVER re-emails.
     */
    public function testDuplicateFinalizeOnEmailedOrderDoesNotResend(): void
    {
        $attempt = $this->newPaidAttempt()->markFinalized(77);
        $this->repository->method('lockByAppTransId')->willReturn($attempt);

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $existing = $this->newOrder(77, Order::STATE_PROCESSING, '000000123', $payment);
        $existing->method('getEmailSent')->willReturn(1);
        $this->orderRepository->method('get')->with(77)->willReturn($existing);
        $this->cartManagement->expects($this->never())->method('placeOrder');

        $this->orderSender->expects($this->never())->method('send');

        $order = $this->finalizer->finalizeOrRecover($attempt, '240801000001');

        $this->assertSame($existing, $order);
    }

    /**
     * EMAIL 7: a FINALIZED duplicate on a NOT-yet-emailed order backfills
     * exactly one confirmation send (crash between commit and send, or a
     * previous send failure) — the finalizeOrRecover retry driver.
     */
    public function testDuplicateFinalizeBackfillsMissingEmail(): void
    {
        $attempt = $this->newPaidAttempt()->markFinalized(77);
        $this->repository->method('lockByAppTransId')->willReturn($attempt);

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $existing = $this->newOrder(77, Order::STATE_PROCESSING, '000000123', $payment);
        $existing->method('getEmailSent')->willReturn(null);
        $this->orderRepository->method('get')->with(77)->willReturn($existing);

        $this->orderSender->expects($this->once())->method('send');

        $order = $this->finalizer->finalizeOrRecover($attempt, '240801000001');

        $this->assertSame($existing, $order);
    }

    /**
     * EMAIL 3: a capture failure rolls the whole unit back — the email is
     * NEVER sent for an order that was not committed.
     */
    public function testCaptureFailureNeverSendsEmail(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubMatchingQuote();

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $payment->method('capture')->willThrowException(new LocalizedException(__('Capture failed.')));
        $order = $this->newOrder(88, Order::STATE_PENDING_PAYMENT, '000000123', $payment);
        $this->cartManagement->method('placeOrder')->willReturn(88);
        $this->orderRepository->method('get')->with(88)->willReturn($order);

        $this->connection->expects($this->once())->method('rollBack');
        $this->orderSender->expects($this->never())->method('send');

        $this->expectException(LocalizedException::class);
        $this->finalizer->finalizeOrRecover($attempt, '240801000001');
    }

    /**
     * EMAIL 4: a contract mismatch refuses placement and sends NO email —
     * no order was created and the unit rolled back.
     */
    public function testContractMismatchNeverSendsEmail(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubMatchingQuote(currentVndAmount: 700000);
        $this->lifecycle->expects($this->once())->method('recordContractMismatch');

        $this->orderSender->expects($this->never())->method('send');

        $this->expectException(ContractMismatchException::class);
        $this->finalizer->finalizeOrRecover($attempt, '240801000001');
    }

    /**
     * EMAIL 5: a failed email send is non-fatal — the order is still
     * returned FINALIZED, the transaction is NOT rolled back (a payment must
     * never be rolled back because of mail), and the failure is logged.
     */
    public function testEmailFailureKeepsOrderFinalized(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubMatchingQuote();

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $payment->method('capture');
        $payment->method('prependMessage');
        $order = $this->newOrder(88, Order::STATE_PENDING_PAYMENT, '000000123', $payment);
        $this->cartManagement->method('placeOrder')->willReturn(88);
        $this->orderRepository->method('get')->with(88)->willReturn($order);

        $this->connection->expects($this->once())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');
        $this->orderSender->expects($this->once())->method('send')
            ->willThrowException(new \RuntimeException('SMTP transport error.'));
        $this->logger->expects($this->once())->method('critical');

        $result = $this->finalizer->finalizeOrRecover($attempt, '240801000001');

        $this->assertSame($order, $result);
        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $this->saved->getPaymentStatus());
    }

    /**
     * EMAIL 8 (TASK-CG6BM7 concurrency): two concurrent finalizers serialize
     * on the attempt row lock; the one LOSING the conditional dispatch claim
     * (the resource-level atomic UPDATE grants it to exactly one caller)
     * must NOT send — no duplicate dispatch is possible.
     */
    public function testConcurrentFinalizerLosingClaimDoesNotSend(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubMatchingQuote();

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $payment->method('capture');
        $payment->method('prependMessage');
        $order = $this->newOrder(88, Order::STATE_PENDING_PAYMENT, '000000123', $payment);
        $this->cartManagement->method('placeOrder')->willReturn(88);
        $this->orderRepository->method('get')->with(88)->willReturn($order);

        // The concurrent winner already claimed the dispatch.
        $this->emailClaimGranted = false;
        $this->orderSender->expects($this->never())->method('send');
        $this->repository->expects($this->never())->method('releaseEmailDispatch');

        $this->connection->expects($this->once())->method('commit');

        $result = $this->finalizer->finalizeOrRecover($attempt, '240801000001');

        $this->assertSame($order, $result);
    }

    /**
     * EMAIL 9 (TASK-CG6BM7 retry semantics): a failed send releases the
     * dispatch claim (token-guarded) so the next finalizeOrRecover driver
     * retries immediately — the failed send stays non-fatal.
     */
    public function testEmailFailureReleasesDispatchClaim(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubMatchingQuote();

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $payment->method('capture');
        $payment->method('prependMessage');
        $order = $this->newOrder(88, Order::STATE_PENDING_PAYMENT, '000000123', $payment);
        $this->cartManagement->method('placeOrder')->willReturn(88);
        $this->orderRepository->method('get')->with(88)->willReturn($order);

        $this->orderSender->expects($this->once())->method('send')
            ->willThrowException(new \RuntimeException('SMTP transport error.'));
        $this->logger->expects($this->atLeastOnce())->method('critical');
        $this->repository->expects($this->once())->method('releaseEmailDispatch');

        $this->finalizer->finalizeOrRecover($attempt, '240801000001');
    }

    /**
     * EMAIL 10 (TASK-CG6BM7): the claim marks the in-flight dispatch only —
     * after a SUCCESSFUL send it is released again (the durable "sent"
     * record is the order's email_sent, kept by OrderSender).
     */
    public function testSuccessfulSendReleasesDispatchClaim(): void
    {
        $attempt = $this->newPaidAttempt();
        $this->repository->method('lockByAppTransId')->willReturn($attempt);
        $this->stubSave();
        $this->stubMatchingQuote();

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getMethod')->willReturn('zalopay');
        $payment->method('capture');
        $payment->method('prependMessage');
        $order = $this->newOrder(88, Order::STATE_PENDING_PAYMENT, '000000123', $payment);
        $this->cartManagement->method('placeOrder')->willReturn(88);
        $this->orderRepository->method('get')->with(88)->willReturn($order);

        $this->orderSender->expects($this->once())->method('send');
        $this->repository->expects($this->once())->method('releaseEmailDispatch');

        $this->finalizer->finalizeOrRecover($attempt, '240801000001');
    }

    // ---- helpers ----

    /**
     * @return void
     */
    private function stubSave(): void
    {
        $this->saved = null;
        $this->repository->method('save')->willReturnCallback(
            function (PaymentAttemptInterface $attempt) {
                $this->saved = $attempt;

                return $attempt;
            }
        );
    }

    /**
     * Current quote for the attempt: active, ZaloPay, matching amount and
     * fingerprint unless a flag says otherwise.
     *
     * @param bool $active
     * @param string $quoteMethod
     * @param int $currentVndAmount
     * @param bool $hashMatches
     * @return void
     */
    private function stubMatchingQuote(
        bool $active = true,
        string $quoteMethod = 'zalopay',
        int $currentVndAmount = 100000,
        bool $hashMatches = true
    ): void {
        $payment = $this->createMock(\Magento\Quote\Model\Quote\Payment::class);
        $payment->method('getMethod')->willReturn($quoteMethod);

        $quote = $this->createMock(QuoteStub::class);
        $quote->method('getIsActive')->willReturn($active);
        $quote->method('getPayment')->willReturn($payment);
        $quote->method('collectTotals')->willReturnSelf();
        $quote->method('getQuoteCurrencyCode')->willReturn('VND');
        $quote->method('getGrandTotal')->willReturn(100.0);

        $this->cartRepository->method('get')->with(42)->willReturn($quote);
        $this->rate->method('getVndAmountByCurrency')->willReturn((float)$currentVndAmount);
        $this->fingerprint->method('calculate')->willReturn(self::PERSISTED_HASH);
        $this->fingerprint->method('matches')->willReturn($hashMatches);
    }

    /**
     * @param array $orders
     * @return void
     */
    private function stubOrderSearch(array $orders): void
    {
        $results = $this->createMock(SearchResultsInterface::class);
        $results->method('getItems')->willReturn($orders);
        $this->orderRepository->method('getList')->willReturn($results);
    }

    /**
     * @param int $entityId
     * @param string $state
     * @param string $incrementId
     * @param OrderPayment|MockObject|null $payment
     * @return Order|MockObject
     */
    private function newOrder(
        int $entityId,
        string $state,
        string $incrementId = '000000123',
        ?OrderPayment $payment = null
    ): Order {
        $order = $this->createMock(Order::class);
        $order->method('getEntityId')->willReturn($entityId);
        $order->method('getIncrementId')->willReturn($incrementId);
        $order->method('getState')->willReturn($state);
        $order->method('getQuoteId')->willReturn(null);
        if ($payment !== null) {
            $order->method('getPayment')->willReturn($payment);
        }

        return $order;
    }

    /**
     * @return PaymentAttempt
     */
    private function newPaidAttempt(): PaymentAttempt
    {
        $attempt = $this->newActiveAttempt()->markPaid('240801000001');
        $attempt->setContractHash(self::PERSISTED_HASH);

        return $attempt;
    }

    /**
     * @return PaymentAttempt
     */
    private function newActiveAttempt(): PaymentAttempt
    {
        $attempt = $this->newAttemptModel();
        $attempt->setEntityId(9);
        $attempt->setQuoteId(42);
        $attempt->setReservedOrderId('000000123');
        $attempt->setAppTransId('260826_1000_000000123');
        $attempt->setAmount(100000);
        $attempt->setCurrency(PaymentAttemptInterface::CURRENCY_VND);
        $attempt->setExpiresAt('2099-01-01 00:00:00');
        $attempt->setPaymentStatus(PaymentAttemptInterface::STATUS_INITIATED);
        $attempt->markActive('https://pay.zalopay.vn/order/abc');

        return $attempt;
    }

    /**
     * PaymentAttempt extends AbstractModel — constructor needs Context/Registry.
     *
     * @return PaymentAttempt
     */
    private function newAttemptModel(): PaymentAttempt
    {
        return new PaymentAttempt(
            $this->createMock(\Magento\Framework\Model\Context::class),
            $this->createMock(\Magento\Framework\Registry::class),
            $this->newResourceStub()
        );
    }

    /**
     * An injected resource keeps _init() away from the (unit-test absent)
     * ObjectManager while providing the entity id field name.
     *
     * @return \Secomm\ZaloPay\Model\ResourceModel\PaymentAttemptResource|\PHPUnit\Framework\MockObject\MockObject
     */
    private function newResourceStub()
    {
        $resource = $this->getMockBuilder(\Secomm\ZaloPay\Model\ResourceModel\PaymentAttemptResource::class)
            ->disableOriginalConstructor()
            ->getMock();
        $resource->method('getIdFieldName')->willReturn(PaymentAttemptInterface::ENTITY_ID);

        return $resource;
    }
}
