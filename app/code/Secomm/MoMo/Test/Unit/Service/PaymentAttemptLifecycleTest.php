<?php
/**
 * Unit test for the canonical PaymentAttemptLifecycle mutations (MOMO-01).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\DB\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Api\Data\PaymentAttemptInterface;
use Secomm\MoMo\Api\PaymentAttemptRepositoryInterface;
use Secomm\MoMo\Model\PaymentAttempt;
use Secomm\MoMo\Service\PaymentAttemptLifecycle;

/**
 * Verifies the locked lifecycle: PAID where the fresh state permits,
 * money-real state never regressed, terminal states never broadened,
 * conflicts quarantined with structured codes, idempotent duplicates and
 * save-only-when-changed.
 */
class PaymentAttemptLifecycleTest extends TestCase
{
    private PaymentAttemptRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository;

    private AdapterInterface&\PHPUnit\Framework\MockObject\MockObject $connection;

    private PaymentAttemptLifecycle $lifecycle;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->repository = $this->createMock(PaymentAttemptRepositoryInterface::class);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $logger = $this->createMock(LoggerInterface::class);

        $this->lifecycle = new PaymentAttemptLifecycle($this->repository, $resourceConnection, $logger);
    }

    /**
     * ACTIVE -> PAID on an authoritative proof, identity recorded, saved.
     *
     * @return void
     */
    public function testRecordVerifiedPaidTransitionsActiveToPaid(): void
    {
        $attempt = $this->attempt('active');
        $this->lock($attempt);
        $this->connection->expects($this->once())->method('commit');
        $this->repository->expects($this->once())->method('save')->willReturnArgument(0);

        $fresh = $this->lifecycle->recordVerifiedPaid('MOMOREF', '987654321');

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertSame('987654321', $attempt->getProviderTransactionId());
    }

    /**
     * A duplicate paid claim on FINALIZED is a no-op (no save, still bound).
     *
     * @return void
     */
    public function testRecordVerifiedPaidIsIdempotentForFinalized(): void
    {
        $attempt = $this->attempt('finalized', ['order_id' => 5001]);
        $this->lock($attempt);
        $this->repository->expects($this->never())->method('save');

        $fresh = $this->lifecycle->recordVerifiedPaid('MOMOREF', '987654321');

        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $fresh->getPaymentStatus());
        $this->assertSame(5001, $fresh->getOrderId());
    }

    /**
     * A PAID attempt without an identity gets the later one backfilled.
     *
     * @return void
     */
    public function testRecordVerifiedPaidBackfillsMissingTransIdOnPaid(): void
    {
        $attempt = $this->attempt('paid');
        $this->lock($attempt);
        $this->repository->expects($this->once())->method('save')->willReturnArgument(0);

        $fresh = $this->lifecycle->recordVerifiedPaid('MOMOREF', '987654321');

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertSame('987654321', $fresh->getProviderTransactionId());
    }

    /**
     * Two authoritative proofs with DIFFERENT transIds never both drive the
     * lifecycle: the first recorded identity is kept, the conflict is
     * quarantined (AC5).
     *
     * @return void
     */
    public function testConflictingTransIdQuarantines(): void
    {
        $attempt = $this->attempt('paid', ['provider_transaction_id' => '111']);
        $this->lock($attempt);
        $this->repository->expects($this->once())->method('save')->willReturnArgument(0);

        $fresh = $this->lifecycle->recordVerifiedPaid('MOMOREF', '222');

        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(PaymentAttemptInterface::RECON_PROVIDER_TX_CONFLICT, $fresh->getReconciliationCode());
        $this->assertSame('111', $fresh->getProviderTransactionId());
        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
    }

    /**
     * Authoritative PAID evidence on a FAILED attempt: the terminal state is
     * NOT broadened, the evidence + identity are persisted for manual
     * reconciliation.
     *
     * @return void
     */
    public function testPaidEvidenceOnFailedAttemptRecordsEvidenceOnly(): void
    {
        $attempt = $this->attempt('failed');
        $this->lock($attempt);
        $this->repository->expects($this->once())->method('save')->willReturnArgument(0);

        $fresh = $this->lifecycle->recordVerifiedPaid('MOMOREF', '987654321');

        $this->assertSame(PaymentAttemptInterface::STATUS_FAILED, $fresh->getPaymentStatus());
        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_LATE_PAID_TERMINAL_STATE,
            $fresh->getReconciliationCode()
        );
        $this->assertSame('987654321', $fresh->getProviderTransactionId());
    }

    /**
     * Authoritative failure where the state permits: INITIATED/ACTIVE ->
     * FAILED with the evidence message.
     *
     * @return void
     */
    public function testRecordVerifiedFailureTransitionsActiveToFailed(): void
    {
        $attempt = $this->attempt('active');
        $this->lock($attempt);
        $this->repository->expects($this->once())->method('save')->willReturnArgument(0);

        $fresh = $this->lifecycle->recordVerifiedFailure('MOMOREF', 'resultCode 700', 'failed');

        $this->assertSame(PaymentAttemptInterface::STATUS_FAILED, $fresh->getPaymentStatus());
        $this->assertSame('resultCode 700', $fresh->getLastError());
    }

    /**
     * A failure claim against PAID never regresses the money-real state:
     * sticky provider_state_conflict quarantine instead.
     *
     * @return void
     */
    public function testFailureClaimNeverRegressesPaid(): void
    {
        $attempt = $this->attempt('paid', ['provider_transaction_id' => '111']);
        $this->lock($attempt);
        $this->repository->expects($this->once())->method('save')->willReturnArgument(0);

        $fresh = $this->lifecycle->recordVerifiedFailure('MOMOREF', 'resultCode 700', 'failed');

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_PROVIDER_STATE_CONFLICT,
            $fresh->getReconciliationCode()
        );
    }

    /**
     * A failure claim against FINALIZED is stale — no mutation.
     *
     * @return void
     */
    public function testFailureClaimIsNoopForFinalized(): void
    {
        $attempt = $this->attempt('finalized', ['order_id' => 5001]);
        $this->lock($attempt);
        $this->repository->expects($this->never())->method('save');

        $fresh = $this->lifecycle->recordVerifiedFailure('MOMOREF', 'late cancel', 'failed');

        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $fresh->getPaymentStatus());
        $this->assertFalse($fresh->getRequiresReconciliation());
    }

    /**
     * A verified amount mismatch keeps the money-real state and quarantines
     * with the amount_mismatch code (AC4).
     *
     * @return void
     */
    public function testRecordAmountMismatchQuarantines(): void
    {
        $attempt = $this->attempt('active');
        $this->lock($attempt);
        $this->repository->expects($this->once())->method('save')->willReturnArgument(0);

        $fresh = $this->lifecycle->recordAmountMismatch('MOMOREF', 200000, 'IPN', '987654321');

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(PaymentAttemptInterface::RECON_AMOUNT_MISMATCH, $fresh->getReconciliationCode());
        $this->assertStringContainsString('amount mismatch', (string)$fresh->getLastError());
    }

    /**
     * When the LOCKED row actually matches the paid amount the caller's
     * stale-copy comparison is corrected into normal verified-paid handling.
     *
     * @return void
     */
    public function testRecordAmountMismatchWithMatchingRowAppliesVerifiedPaid(): void
    {
        $attempt = $this->attempt('active');
        $this->lock($attempt);
        $this->repository->expects($this->once())->method('save')->willReturnArgument(0);

        $fresh = $this->lifecycle->recordAmountMismatch('MOMOREF', 150000, 'Return', '987654321');

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertSame('987654321', $fresh->getProviderTransactionId());
        $this->assertFalse($fresh->getRequiresReconciliation());
    }

    /**
     * Verified money without a provable provider identity: money-real PAID +
     * provider_transaction_unavailable quarantine — never an order (AC4).
     *
     * @return void
     */
    public function testRecordProviderIdentityUnavailableQuarantines(): void
    {
        $attempt = $this->attempt('active');
        $this->lock($attempt);
        $this->repository->expects($this->once())->method('save')->willReturnArgument(0);

        $fresh = $this->lifecycle->recordProviderIdentityUnavailable('MOMOREF', 'IPN');

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(
            PaymentAttemptInterface::RECON_PROVIDER_TX_UNAVAILABLE,
            $fresh->getReconciliationCode()
        );
    }

    /**
     * A contract-mismatch refusal on a non-finalized attempt quarantines
     * with the contract_mismatch code.
     *
     * @return void
     */
    public function testRecordContractMismatchQuarantinesNonFinalized(): void
    {
        $attempt = $this->attempt('paid');
        $this->lock($attempt);
        $this->repository->expects($this->once())->method('save')->willReturnArgument(0);

        $fresh = $this->lifecycle->recordContractMismatch('MOMOREF', 'quote total changed');

        $this->assertSame(PaymentAttemptInterface::STATUS_PAID, $fresh->getPaymentStatus());
        $this->assertTrue($fresh->getRequiresReconciliation());
        $this->assertSame(PaymentAttemptInterface::RECON_CONTRACT_MISMATCH, $fresh->getReconciliationCode());
    }

    /**
     * A contract-mismatch refusal on a FINALIZED attempt is evidence-only:
     * the bound order's attempt is never poisoned into quarantine.
     *
     * @return void
     */
    public function testRecordContractMismatchIsEvidenceOnlyForFinalized(): void
    {
        $attempt = $this->attempt('finalized', ['order_id' => 5001]);
        $this->lock($attempt);
        $this->repository->expects($this->once())->method('save')->willReturnArgument(0);

        $fresh = $this->lifecycle->recordContractMismatch('MOMOREF', 'quote total changed');

        $this->assertSame(PaymentAttemptInterface::STATUS_FINALIZED, $fresh->getPaymentStatus());
        $this->assertFalse($fresh->getRequiresReconciliation());
        $this->assertStringContainsString(
            'Contract mismatch (order already bound',
            (string)$fresh->getLastError()
        );
    }

    /**
     * An unknown order_ref throws and rolls back.
     *
     * @return void
     */
    public function testUnknownOrderRefThrowsAndRollsBack(): void
    {
        $this->repository->method('lockByOrderRef')->willReturn(null);
        $this->connection->expects($this->once())->method('rollBack');
        $this->repository->expects($this->never())->method('save');

        $this->expectException(LocalizedException::class);

        $this->lifecycle->recordVerifiedPaid('UNKNOWN', '987654321');
    }

    /**
     * A real attempt in the given status.
     *
     * @param string $status
     * @param array $extra Field values applied on top (order_id, provider id...).
     * @return PaymentAttempt
     */
    private function attempt(string $status, array $extra = []): PaymentAttempt
    {
        $attempt = new PaymentAttempt($this->createMock(Context::class), $this->createMock(Registry::class));
        $attempt->setEntityId(7);
        $attempt->setQuoteId(42);
        $attempt->setOrderRef('MOMOREF');
        $attempt->setAmount(150000);
        $attempt->setPaymentStatus($status);
        foreach ($extra as $field => $value) {
            $attempt->setData($field, $value);
        }

        return $attempt;
    }

    /**
     * Lock wiring: the repository serves the attempt under lock.
     *
     * @param PaymentAttempt $attempt
     * @return void
     */
    private function lock(PaymentAttempt $attempt): void
    {
        $this->repository->method('lockByOrderRef')->willReturn($attempt);
    }
}
