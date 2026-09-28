<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Cod\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Cod\Model\CollectionLedger;
use Secomm\Cod\Model\CodCollectionAttempt;

/**
 * TASK-DFGFZ9 phase 3 (DEC-TASKDFGFZ9-003) — ledger SQL contract: idempotent recordPending
 * that never downgrades a submitted outcome (SUBMITTED|RECOVERED), guarded close-outs
 * (FAILED|UNKNOWN only, never SUBMITTED|RECOVERED), frozen lookup excluding FAILED, and a
 * prior lookup excluding ONLY the current (carrier, reference) — a null attempt excludes
 * nothing (fail-safe).
 */
class CollectionLedgerTest extends TestCase
{
    private ResourceConnection&MockObject $resourceConnection;

    private AdapterInterface&MockObject $connection;

    private Select&MockObject $select;

    /** @var array<string, string> keyed "carrier|reference" — simulated ledger rows (status) */
    private array $rows = [];

    /** @var array<array<string, mixed>> captured insert rows */
    private array $inserts = [];

    /** @var array<array<string, mixed>> captured update binds [data, where] */
    private array $updates = [];

    /** @var array{carrier_code: string, provider_reference: string, amount: string, currency: string}|null */
    private ?array $priorRow = null;

    /** @var array{amount: string, currency: string}|null */
    private ?array $frozenRow = null;

    /** Ledger key probed by recordPending's status check. */
    private string $probeKey = 'ghtk|ghtk-1001-1';

    /** When true, the simulated INSERT raises DuplicateException (unique-key contention). */
    private bool $insertThrowsDuplicate = false;

    /** Row served by fetchRow (frozen/prior lookup), configured per test. */
    private ?array $pendingFetchRow = null;

    protected function setUp(): void
    {
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->select = $this->createMock(Select::class);
        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('order')->willReturnSelf();
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        $this->resourceConnection->method('getTableName')
            ->with(CollectionLedger::TABLE)
            ->willReturn(CollectionLedger::TABLE);

        $this->rows = [];
        $this->inserts = [];
        $this->updates = [];
        $this->insertThrowsDuplicate = false;
        $this->priorRow = null;
        $this->frozenRow = null;
    }

    private function ledger(): CollectionLedger
    {
        return new CollectionLedger($this->resourceConnection);
    }

    private function attempt(string $carrier = 'ghtk', string $reference = 'ghtk-1001-1'): CodCollectionAttempt
    {
        return new CodCollectionAttempt($carrier, $reference);
    }

    private function wireReads(): void
    {
        // Simulated reads: the ledger issues fetchOne for recordPending's status probe and
        // fetchRow for the frozen/prior lookups — each test configures the served values.
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchOne')->willReturnCallback(
            function (Select $select): ?string {
                $key = $this->probeKey;
                return $this->rows[$key] ?? null;
            }
        );
        $this->connection->method('fetchRow')->willReturnCallback(
            function (Select $select): ?array {
                // Order matters in the ledger: frozen lookup (attempt-scoped) runs before the
                // prior lookup in the resolver, but each test drives one path at a time —
                // serve the row configured for whichever lookup this test exercises.
                return $this->pendingFetchRow;
            }
        );
        $this->connection->method('insert')->willReturnCallback(
            function (string $table, array $row): int {
                if ($this->insertThrowsDuplicate) {
                    throw new \Magento\Framework\DB\Adapter\DuplicateException(
                        'SQLSTATE[23000]: Integrity constraint violation: 1062 duplicate entry',
                        23000
                    );
                }
                $this->inserts[] = $row;
                $this->rows[$row['carrier_code'] . '|' . $row['provider_reference']] = CollectionLedger::STATUS_PENDING;

                return 1;
            }
        );
        $this->connection->method('update')->willReturnCallback(
            function (string $table, array $data, array $where): int {
                $this->updates[] = [$data, $where];

                return 1;
            }
        );
    }

    public function testRecordPendingInsertsWhenAbsent(): void
    {
        $this->probeKey = 'ghtk|ghtk-1001-1';
        $this->wireReads();

        $this->ledger()->recordPending($this->attempt(), 7, 1250000.0, 'VND');

        $this->assertCount(1, $this->inserts);
        $this->assertSame(7, $this->inserts[0]['magento_order_id']);
        $this->assertSame(1250000.0, $this->inserts[0]['amount']);
        $this->assertSame('VND', $this->inserts[0]['currency']);
        $this->assertSame('ghtk', $this->inserts[0]['carrier_code']);
        $this->assertSame(CollectionLedger::STATUS_PENDING, $this->inserts[0]['status']);
        $this->assertSame(7, $this->inserts[0]['active_order_claim'], 'the INSERT arms the per-order claim');
    }

    public function testRecordPendingNeverDowngradesSubmittedRow(): void
    {
        $this->probeKey = 'ghtk|ghtk-1001-1';
        $this->insertThrowsDuplicate = true; // our key already exists → 1062 → probe
        $this->rows['ghtk|ghtk-1001-1'] = CollectionLedger::STATUS_SUBMITTED;
        $this->wireReads();

        $this->ledger()->recordPending($this->attempt(), 7, 1250000.0, 'VND');

        $this->assertSame([], $this->inserts, 'a submitted outcome is never re-armed');
        $this->assertSame([], $this->updates);
    }

    public function testRecordPendingNeverDowngradesRecoveredRow(): void
    {
        $this->probeKey = 'ghtk|ghtk-1001-1';
        $this->insertThrowsDuplicate = true; // our key already exists → 1062 → probe
        $this->rows['ghtk|ghtk-1001-1'] = CollectionLedger::STATUS_RECOVERED;
        $this->wireReads();

        $this->ledger()->recordPending($this->attempt(), 7, 1250000.0, 'VND');

        $this->assertSame([], $this->inserts);
        $this->assertSame([], $this->updates);
    }

    public function testRecordPendingRefreshesPendingRow(): void
    {
        $this->probeKey = 'ghtk|ghtk-1001-1';
        $this->insertThrowsDuplicate = true; // our key already exists → 1062 → probe → re-arm
        $this->rows['ghtk|ghtk-1001-1'] = CollectionLedger::STATUS_PENDING;
        $this->wireReads();

        $this->ledger()->recordPending($this->attempt(), 7, 990000.0, 'VND');

        $this->assertSame([], $this->inserts, 'an existing row is refreshed, not duplicated');
        $this->assertCount(1, $this->updates);
        $this->assertSame(990000.0, $this->updates[0][0]['amount']);
        $this->assertNull($this->updates[0][0]['reason_code']);
    }

    public function testDuplicateInsertWithPendingRowReArmsAsPending(): void
    {
        $this->probeKey = 'ghtk|ghtk-1001-1';
        $this->insertThrowsDuplicate = true;
        $this->rows['ghtk|ghtk-1001-1'] = CollectionLedger::STATUS_UNKNOWN;
        $this->wireReads();

        $this->ledger()->recordPending($this->attempt(), 7, 800000.0, 'VND');

        $this->assertSame([], $this->inserts);
        $this->assertCount(1, $this->updates);
        $this->assertSame(CollectionLedger::STATUS_PENDING, $this->updates[0][0]['status'], 're-arm restores PENDING');
        $this->assertSame(7, $this->updates[0][0]['active_order_claim'], 're-arm re-claims the order');
        $this->assertSame(800000.0, $this->updates[0][0]['amount']);
    }

    public function testDuplicateInsertWithSubmittedRowNeverWrites(): void
    {
        $this->probeKey = 'ghtk|ghtk-1001-1';
        $this->insertThrowsDuplicate = true;
        $this->rows['ghtk|ghtk-1001-1'] = CollectionLedger::STATUS_SUBMITTED;
        $this->wireReads();

        $this->ledger()->recordPending($this->attempt(), 7, 800000.0, 'VND');

        $this->assertSame([], $this->inserts);
        $this->assertSame([], $this->updates, 'a submitted outcome is never re-armed');
    }

    public function testDuplicateInsertForAnotherAttemptRaisesClaimConflict(): void
    {
        // Our (carrier, reference) key is ABSENT → the 1062 came from the per-order claim:
        // a different attempt holds the order — fail with a carrier-naming conflict.
        $this->probeKey = 'ghn|GHNS900101';
        $this->insertThrowsDuplicate = true;
        $this->wireReads();
        $this->pendingFetchRow = ['carrier_code' => 'ghtk', 'provider_reference' => 'ghtk-900101-1'];

        try {
            $this->ledger()->recordPending($this->attempt('ghn', 'GHNS900101'), 900101, 500000.0, 'VND');
            $this->fail('Expected CodClaimConflictException');
        } catch (\Secomm\Cod\Model\CodClaimConflictException $e) {
            $this->assertStringContainsString('ghtk', $e->getMessage());
            $this->assertStringContainsString('ghtk-900101-1', $e->getMessage());
        }
    }

    public function testDuplicateInsertWithInvisibleHolderStillConflicts(): void
    {
        $this->probeKey = 'ghn|GHNS900101';
        $this->insertThrowsDuplicate = true;
        $this->wireReads();
        $this->pendingFetchRow = null; // holder not visible — still a conflict, retryable

        try {
            $this->ledger()->recordPending($this->attempt('ghn', 'GHNS900101'), 900101, 500000.0, 'VND');
            $this->fail('Expected CodClaimConflictException');
        } catch (\Secomm\Cod\Model\CodClaimConflictException $e) {
            $this->assertStringContainsString('held by another shipment attempt', $e->getMessage());
        }
    }

    public function testMarkNotSubmittedFailedReleasesTheClaim(): void
    {
        $this->wireReads();

        $this->ledger()->markNotSubmitted($this->attempt(), CollectionLedger::STATUS_FAILED, 'BUSINESS');

        $this->assertArrayHasKey('active_order_claim', $this->updates[0][0], 'FAILED writes the claim column');
        $this->assertNull($this->updates[0][0]['active_order_claim'], 'FAILED releases the order claim');
    }

    public function testMarkNotSubmittedUnknownKeepsTheClaim(): void
    {
        $this->wireReads();

        $this->ledger()->markNotSubmitted($this->attempt(), CollectionLedger::STATUS_UNKNOWN, 'TECHNICAL_ERROR');

        $this->assertArrayNotHasKey('active_order_claim', $this->updates[0][0], 'UNKNOWN keeps the claim (untouched)');
    }

    public function testMarkSubmittedSetsSubmittedStatus(): void
    {
        $this->wireReads();

        $this->ledger()->markSubmitted($this->attempt(), false);

        $this->assertSame(CollectionLedger::STATUS_SUBMITTED, $this->updates[0][0]['status']);
        $this->assertNull($this->updates[0][0]['reason_code']);
    }

    public function testMarkSubmittedRecoveredFlag(): void
    {
        $this->wireReads();

        $this->ledger()->markSubmitted($this->attempt(), true);

        $this->assertSame(CollectionLedger::STATUS_RECOVERED, $this->updates[0][0]['status']);
    }

    public function testMarkNotSubmittedAcceptsFailedAndUnknownOnly(): void
    {
        $this->wireReads();

        $this->ledger()->markNotSubmitted($this->attempt(), CollectionLedger::STATUS_FAILED, 'BUSINESS');
        $this->ledger()->markNotSubmitted($this->attempt(), CollectionLedger::STATUS_UNKNOWN, 'TECHNICAL_ERROR');

        $this->assertSame(CollectionLedger::STATUS_FAILED, $this->updates[0][0]['status']);
        $this->assertSame(CollectionLedger::STATUS_UNKNOWN, $this->updates[1][0]['status']);
    }

    public function testMarkNotSubmittedRejectsOtherStatuses(): void
    {
        $this->wireReads();

        $this->expectException(\InvalidArgumentException::class);
        $this->ledger()->markNotSubmitted($this->attempt(), CollectionLedger::STATUS_PENDING, 'nope');
    }

    public function testFindFrozenAmountReturnsConfiguredRow(): void
    {
        $this->wireReads();
        $this->pendingFetchRow = ['amount' => '750000.0000', 'currency' => 'VND'];

        $frozen = $this->ledger()->findFrozenAmount($this->attempt());

        $this->assertSame(['amount' => '750000.0000', 'currency' => 'VND'], $frozen);
    }

    public function testFindFrozenAmountReturnsNullWhenAbsent(): void
    {
        $this->wireReads();

        $this->assertNull($this->ledger()->findFrozenAmount($this->attempt()));
    }

    public function testFindCollectedPriorReturnsRow(): void
    {
        $this->wireReads();
        $this->pendingFetchRow = [
            'carrier_code' => 'ghn',
            'provider_reference' => 'GHNS41',
            'amount' => '500000.0000',
            'currency' => 'VND',
        ];

        $prior = $this->ledger()->findCollectedPrior(7, $this->attempt('ghtk', 'ghtk-1001-2'));

        $this->assertSame('ghn', $prior['carrier_code']);
        $this->assertSame('GHNS41', $prior['provider_reference']);
    }

    public function testFindCollectedPriorWithoutAttemptExcludesNothing(): void
    {
        $this->wireReads();
        $this->pendingFetchRow = ['carrier_code' => 'ghtk', 'provider_reference' => 'ghtk-1001-1', 'amount' => '1', 'currency' => 'VND'];

        // A null-attempt caller is still held to the one-collection rule: no exclusion clause.
        $prior = $this->ledger()->findCollectedPrior(7, null);

        $this->assertNotNull($prior);
    }
}
