<?php
/**
 * Unit tests for the refund request manager (MOMO-02).
 *
 * Verifies the submission guards: at most one open refund per payment
 * (pending and unknown rows both block with the row requestId in the
 * message), the budget drift guard, race-safe insertion, and that outcome
 * recording routes to the correct guarded transition with last_error
 * reserved for UNKNOWN rows.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;
use Secomm\MoMo\Api\Data\RefundRequestInterface;
use Secomm\MoMo\Api\RefundRequestRepositoryInterface;
use Secomm\MoMo\Gateway\Request\RefundBuilder;
use Secomm\MoMo\Model\OrderRefBuilder;
use Secomm\MoMo\Model\RefundRequestFactory;
use Secomm\MoMo\Service\RefundClassification;
use Secomm\MoMo\Service\RefundRequestManager;

class RefundRequestManagerTest extends TestCase
{
    private const ORDER_REF = 'MOMO2609180000-SLP-1';
    private const TRANS_ID = '2820086700';

    /**
     * @var RefundRequestRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $repository;

    /**
     * @var RefundRequestManager
     */
    private RefundRequestManager $manager;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->repository = $this->createMock(RefundRequestRepositoryInterface::class);
        $this->manager = new RefundRequestManager(
            $this->repository,
            new RefundRequestFactory(),
            new OrderRefBuilder($this->createMock(DateTime::class))
        );
    }

    /**
     * Build a payment data object mock with a MoMo identity.
     *
     * @param array $additionalInfo
     * @return PaymentDataObjectInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function paymentDO(array $additionalInfo = [
        RefundBuilder::KEY_ORDER_REF => self::ORDER_REF,
        RefundBuilder::KEY_TRANS_ID => self::TRANS_ID,
    ])
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            fn (string $key) => $additionalInfo[$key] ?? null
        );
        $payment->method('getAmountRefunded')->willReturn(100000.0);

        $creditmemo = $this->createMock(Creditmemo::class);
        $invoice = $this->createMock(Invoice::class);
        $invoice->method('getId')->willReturn(7);
        $creditmemo->method('getInvoice')->willReturn($invoice);
        $payment->method('getCreditmemo')->willReturn($creditmemo);

        $order = $this->createMock(OrderAdapterInterface::class);
        $order->method('getId')->willReturn(42);
        $order->method('getOrderIncrementId')->willReturn('200000123');
        $order->method('getStoreId')->willReturn(1);

        $paymentDO = $this->createMock(PaymentDataObjectInterface::class);
        $paymentDO->method('getPayment')->willReturn($payment);
        $paymentDO->method('getOrder')->willReturn($order);

        return $paymentDO;
    }

    /**
     * @param string $status
     * @return RefundRequestInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function openRow(string $status)
    {
        $row = $this->createMock(RefundRequestInterface::class);
        $row->method('getStatus')->willReturn($status);
        $row->method('getRequestId')->willReturn('MOMO-RQ-EXISTING');

        return $row;
    }

    /**
     * AC3: a pending open row blocks the submission and names the request.
     *
     * @return void
     */
    public function testPendingOpenRowBlocksWithRequestId(): void
    {
        $this->repository->method('findOpenByPaymentIdentity')->willReturn($this->openRow('pending'));
        $this->repository->expects($this->never())->method('insert');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('already in progress (request MOMO-RQ-EXISTING)');
        $this->manager->openIdentity($this->paymentDO(), 50000);
    }

    /**
     * AC6: an unknown open row blocks until resolved (no blind resubmission
     * with a new identity).
     *
     * @return void
     */
    public function testUnknownOpenRowBlocksWithResolveGuidance(): void
    {
        $this->repository->method('findOpenByPaymentIdentity')->willReturn($this->openRow('unknown'));
        $this->repository->expects($this->never())->method('insert');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('momo:refund:resolve MOMO-RQ-EXISTING');
        $this->manager->openIdentity($this->paymentDO(), 50000);
    }

    /**
     * Provider-executed refund evidence exceeding accounting blocks refunding.
     *
     * @return void
     */
    public function testBudgetDriftBlocks(): void
    {
        $this->repository->method('findOpenByPaymentIdentity')->willReturn(null);
        $this->repository->method('getSuccessfulTotal')->willReturn(150000);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('exceeds the refunded accounting total (100000)');
        $this->manager->openIdentity($this->paymentDO(), 50000);
    }

    /**
     * AC1/AC2 happy path: a pending row is minted and persisted with the
     * full linkage evidence.
     *
     * @return void
     */
    public function testOpenIdentityMintsAndPersistsRow(): void
    {
        $this->repository->method('findOpenByPaymentIdentity')->willReturn(null);
        $this->repository->method('getSuccessfulTotal')->willReturn(0);
        $captured = null;
        $this->repository->expects($this->once())->method('insert')->willReturnCallback(
            function (RefundRequestInterface $row) use (&$captured): RefundRequestInterface {
                // Mirror the real repository contract: the row comes back open.
                $row->setOpenFlag(true);
                $captured = $row;

                return $row;
            }
        );

        $row = $this->manager->openIdentity($this->paymentDO(), 50000);

        $this->assertSame($captured, $row);
        $this->assertSame(42, $row->getOrderId());
        $this->assertSame('200000123', $row->getOrderIncrementId());
        $this->assertSame(7, $row->getInvoiceId());
        $this->assertSame(self::ORDER_REF, $row->getMomoOrderRef());
        $this->assertSame(self::TRANS_ID, $row->getMomoTransId());
        $this->assertSame(50000, $row->getAmount());
        $this->assertSame('VND', $row->getCurrency());
        $this->assertSame('pending', $row->getStatus());
        $this->assertTrue($row->isOpen());
        $this->assertStringStartsWith(self::ORDER_REF . '-RF', $row->getRefundOrderId());
        $this->assertStringStartsWith(self::ORDER_REF . '-RQ', $row->getRequestId());
        $this->assertNotSame($row->getRefundOrderId(), self::ORDER_REF);
    }

    /**
     * A concurrent open-row insert (UNIQUE key) blocks the loser.
     *
     * @return void
     */
    public function testInsertRaceBlocksLoser(): void
    {
        $this->repository->method('findOpenByPaymentIdentity')->willReturn(null);
        $this->repository->method('getSuccessfulTotal')->willReturn(0);
        $this->repository->method('insert')->willThrowException(new \RuntimeException('Duplicate entry'));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('was opened concurrently');
        $this->manager->openIdentity($this->paymentDO(), 50000);
    }

    /**
     * AC7: SUCCESS evidence finalizes the row.
     *
     * @return void
     */
    public function testRecordOutcomeSuccessFinalizes(): void
    {
        $row = new \Secomm\MoMo\Model\RefundRequest();
        $classification = new RefundClassification('success', 'provider_confirmed', '0', 'ok', '900');
        $this->repository->expects($this->once())->method('finalize')->with($row)->willReturn(true);
        $this->repository->expects($this->never())->method('markUnknown');

        $this->assertTrue($this->manager->recordOutcome($row, $classification));
    }

    /**
     * AC4: FAILED evidence finalizes the row (slot released for retry).
     *
     * @return void
     */
    public function testRecordOutcomeFailedFinalizes(): void
    {
        $row = new \Secomm\MoMo\Model\RefundRequest();
        $classification = new RefundClassification('failed', 'provider_refused', '21', 'no');
        $this->repository->expects($this->once())->method('finalize')->with($row)->willReturn(true);
        $this->repository->expects($this->never())->method('markUnknown');

        $this->assertTrue($this->manager->recordOutcome($row, $classification));
    }

    /**
     * UNKNOWN keeps the row blocking and records the sanitized transport
     * detail as last_error (success/failed rows never carry last_error).
     *
     * @return void
     */
    public function testRecordOutcomeUnknownKeepsBlockingWithError(): void
    {
        $row = new \Secomm\MoMo\Model\RefundRequest();
        $classification = new RefundClassification('unknown', 'transport_error');
        $this->repository->expects($this->once())->method('markUnknown')->with($row)->willReturn(true);
        $this->repository->expects($this->never())->method('finalize');

        $this->assertTrue($this->manager->recordOutcome($row, $classification, 'timeout after 45s'));
        $this->assertSame('timeout after 45s', $row->getLastError());
    }

    /**
     * A missing MoMo transId (no capture ever completed) cannot refund.
     *
     * @return void
     */
    public function testMissingTransIdThrows(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('MoMo transaction reference is missing');
        $this->manager->openIdentity($this->paymentDO([
            RefundBuilder::KEY_ORDER_REF => self::ORDER_REF,
        ]), 50000);
    }

    /**
     * Legacy payments store their transId under `transId` — the chain must
     * fall back to it.
     *
     * @return void
     */
    public function testLegacyTransIdFallback(): void
    {
        $paymentDO = $this->paymentDO([
            RefundBuilder::KEY_ORDER_REF => self::ORDER_REF,
            RefundBuilder::KEY_TRANS_ID_LEGACY => self::TRANS_ID,
        ]);
        $identity = $this->manager->readPaymentIdentity($paymentDO);

        $this->assertSame(self::ORDER_REF, $identity['order_ref']);
        $this->assertSame(self::TRANS_ID, $identity['trans_id']);
    }
}
