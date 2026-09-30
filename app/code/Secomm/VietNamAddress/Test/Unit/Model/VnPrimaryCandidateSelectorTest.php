<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\VietNamAddress\Api\Data\VnPrimaryCandidateSelectionInterface;
use Secomm\VietNamAddress\Model\VnPrimaryCandidateSelector;

/**
 * TASK-KQCX3A — FROZEN selection semantics (directive 2026-09-25 §12/§13/§14/§15/§16):
 * zero → fail closed; sole → selected without is_primary inspection (fast path);
 * >1 + exactly one primary → primary; >1 + zero/multiple primary → deterministic first
 * (code ASC — DB/input-order independent, §14); multiple primary emits the
 * MULTIPLE_PRIMARY_CANDIDATES diagnostic but NEVER fails the flow (§16).
 */
class VnPrimaryCandidateSelectorTest extends TestCase
{
    private ResourceConnection&MockObject $resource;
    private Select&MockObject $select;
    private \Magento\Framework\DB\Adapter\AdapterInterface&MockObject $connection;
    private LoggerInterface&MockObject $logger;
    private VnPrimaryCandidateSelector $selector;

    /** Rows mà connection->fetchCol() trả về cho query is_primary. */
    private array $primaryRows = [];

    /** WHERE segments đã capture (để verify directional query). */
    private array $capturedWhere = [];

    protected function setUp(): void
    {
        $this->resource = $this->createMock(ResourceConnection::class);
        $this->select = $this->createMock(Select::class);
        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnCallback(function (string $cond, $value = null) {
            $this->capturedWhere[] = [$cond, $value];

            return $this->select;
        });
        $this->connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('fetchCol')->willReturnCallback(function (): array {
            return $this->primaryRows;
        });
        $this->resource->method('getTableName')->willReturnCallback(static fn (string $t): string => $t);
        $this->resource->method('getConnection')->willReturn($this->connection);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->selector = new VnPrimaryCandidateSelector($this->resource, $this->logger);
    }

    public function testZeroCandidatesFailClosed(): void
    {
        $this->connection->expects($this->never())->method('fetchCol');

        $selection = $this->selector->selectPrimary('VN_ADMIN_2025', 'VNA25-AAA', 'VN_ADMIN_PRE_2025', []);

        $this->assertSame(VnPrimaryCandidateSelectionInterface::STATUS_NOT_APPLICABLE, $selection->getStatus());
        $this->assertNull($selection->getSelectedCode());
        $this->assertNull($selection->getSelectionReason());
        $this->assertSame(0, $selection->getCandidateCount());
    }

    public function testOneCandidateSelectsSoleWithoutInspectingPrimary(): void
    {
        // §15 fast path — is_primary KHÔNG bao giờ được query cho sole candidate.
        $this->connection->expects($this->never())->method('fetchCol');

        $selection = $this->selector->selectPrimary('VN_ADMIN_2025', 'VNA25-AAA', 'VN_ADMIN_PRE_2025', ['VNAP25-B2B2B2B2B2']);

        $this->assertSame(VnPrimaryCandidateSelectionInterface::STATUS_SELECTED, $selection->getStatus());
        $this->assertSame('VNAP25-B2B2B2B2B2', $selection->getSelectedCode());
        $this->assertSame(VnPrimaryCandidateSelectionInterface::REASON_SOLE_CANDIDATE, $selection->getSelectionReason());
    }

    public function testOnePrimaryFlaggedCandidateStillSelectsSole(): void
    {
        // Sole candidate được chọn kể cả khi is_primary=1 — selector không inspect.
        $this->connection->expects($this->never())->method('fetchCol');

        $selection = $this->selector->selectPrimary('VN_ADMIN_2025', 'VNA25-AAA', 'VN_ADMIN_PRE_2025', ['VNAP25-A1A1A1A1A1']);

        $this->assertSame(VnPrimaryCandidateSelectionInterface::STATUS_SELECTED, $selection->getStatus());
        $this->assertSame('VNAP25-A1A1A1A1A1', $selection->getSelectedCode());
        $this->assertSame(VnPrimaryCandidateSelectionInterface::REASON_SOLE_CANDIDATE, $selection->getSelectionReason());
    }

    public function testExactlyOnePrimaryWinsAmongMultipleCandidates(): void
    {
        $this->primaryRows = ['VNAP25-B2B2B2B2B2'];

        $selection = $this->selector->selectPrimary('VN_ADMIN_2025', 'VNA25-AAA', 'VN_ADMIN_PRE_2025', ['VNAP25-A1A1A1A1A1', 'VNAP25-B2B2B2B2B2', 'VNAP25-M2M2M2M2M2']);

        $this->assertSame(VnPrimaryCandidateSelectionInterface::STATUS_SELECTED, $selection->getStatus());
        $this->assertSame('VNAP25-B2B2B2B2B2', $selection->getSelectedCode());
        $this->assertSame(VnPrimaryCandidateSelectionInterface::REASON_CURATED_PRIMARY, $selection->getSelectionReason());
        $this->assertSame(3, $selection->getCandidateCount());
    }

    public function testZeroPrimarySelectsDeterministicFirst(): void
    {
        $this->primaryRows = [];

        $selection = $this->selector->selectPrimary('VN_ADMIN_2025', 'VNA25-AAA', 'VN_ADMIN_PRE_2025', ['VNAP25-M2M2M2M2M2', 'VNAP25-ZZZ', 'VNAP25-A1A1A1A1A1']);

        $this->assertSame(VnPrimaryCandidateSelectionInterface::STATUS_SELECTED, $selection->getStatus());
        $this->assertSame('VNAP25-A1A1A1A1A1', $selection->getSelectedCode());
        $this->assertSame(VnPrimaryCandidateSelectionInterface::REASON_DETERMINISTIC_FIRST_NO_PRIMARY, $selection->getSelectionReason());
    }

    public function testMultiplePrimarySelectsDeterministicFirstAndEmitsDiagnostic(): void
    {
        // §5 Case E / §16 — curation defect: KHÔNG fail flow, chỉ diagnostic + deterministic first.
        $this->primaryRows = ['VNAP25-M2M2M2M2M2', 'VNAP25-ZZZ'];

        $this->logger->expects($this->once())->method('warning')->with(
            'MULTIPLE_PRIMARY_CANDIDATES',
            $this->callback(function (array $ctx): bool {
                return ($ctx['candidate_count'] ?? null) === 3
                    && ($ctx['primary_count'] ?? null) === 2
                    && ($ctx['selected_source_code'] ?? null) === 'VNAP25-A1A1A1A1A1'
                    && ($ctx['selection_policy'] ?? null) === 'deterministic_first'
                    && ($ctx['source_code'] ?? null) === 'VNA25-AAA';
            })
        );

        $selection = $this->selector->selectPrimary('VN_ADMIN_2025', 'VNA25-AAA', 'VN_ADMIN_PRE_2025', ['VNAP25-M2M2M2M2M2', 'VNAP25-ZZZ', 'VNAP25-A1A1A1A1A1']);

        $this->assertSame(VnPrimaryCandidateSelectionInterface::STATUS_SELECTED, $selection->getStatus());
        $this->assertSame('VNAP25-A1A1A1A1A1', $selection->getSelectedCode());
        $this->assertSame(VnPrimaryCandidateSelectionInterface::REASON_DETERMINISTIC_FIRST_MULTIPLE_PRIMARY, $selection->getSelectionReason());
    }

    public function testSelectionIsIndependentOfInputOrderZeroPrimary(): void
    {
        // §14 — [A,B,C] / [C,A,B] / [B,C,A] cùng kết quả.
        $this->primaryRows = [];

        $permutations = [
            ['VNAP25-A1A1A1A1A1', 'VNAP25-B2B2B2B2B2', 'VNAP25-M2M2M2M2M2'],
            ['VNAP25-M2M2M2M2M2', 'VNAP25-A1A1A1A1A1', 'VNAP25-B2B2B2B2B2'],
            ['VNAP25-B2B2B2B2B2', 'VNAP25-M2M2M2M2M2', 'VNAP25-A1A1A1A1A1'],
        ];

        $selected = [];
        foreach ($permutations as $candidates) {
            $selection = $this->selector->selectPrimary('VN_ADMIN_2025', 'VNA25-AAA', 'VN_ADMIN_PRE_2025', $candidates);
            $selected[] = $selection->getSelectedCode();
        }

        $this->assertSame(['VNAP25-A1A1A1A1A1', 'VNAP25-A1A1A1A1A1', 'VNAP25-A1A1A1A1A1'], $selected);
    }

    public function testSelectionIsIndependentOfInputOrderMultiplePrimary(): void
    {
        // §14 — multiple primary cũng phải deterministic-first (không fail, không tie-break theo order).
        $this->primaryRows = ['VNAP25-ZZZ', 'VNAP25-M2M2M2M2M2'];

        $permutations = [
            ['VNAP25-ZZZ', 'VNAP25-M2M2M2M2M2', 'VNAP25-A1A1A1A1A1'],
            ['VNAP25-A1A1A1A1A1', 'VNAP25-ZZZ', 'VNAP25-M2M2M2M2M2'],
            ['VNAP25-M2M2M2M2M2', 'VNAP25-A1A1A1A1A1', 'VNAP25-ZZZ'],
        ];

        $selected = [];
        foreach ($permutations as $candidates) {
            $selection = $this->selector->selectPrimary('VN_ADMIN_2025', 'VNA25-AAA', 'VN_ADMIN_PRE_2025', $candidates);
            $selected[] = $selection->getSelectedCode();
        }

        $this->assertSame(['VNAP25-A1A1A1A1A1', 'VNAP25-A1A1A1A1A1', 'VNAP25-A1A1A1A1A1'], $selected);
    }

    public function testQueryIsDirectionalSourceToTarget(): void
    {
        // Directional enforcement: query is_primary chỉ theo hướng resolution source → target.
        $this->primaryRows = [];
        $this->selector->selectPrimary('VN_ADMIN_2025', 'VNA25-AAA', 'VN_ADMIN_PRE_2025', ['VNAP25-A1A1A1A1A1', 'VNAP25-B2B2B2B2B2']);

        $conditions = array_column($this->capturedWhere, 0);
        $this->assertContains('m.source_scheme = ?', $conditions);
        $this->assertContains('m.source_code = ?', $conditions);
        $this->assertContains('m.target_scheme = ?', $conditions);
        foreach ($conditions as $condition) {
            $this->assertStringNotContainsString('source_code = m.', $condition);
        }
    }
}
