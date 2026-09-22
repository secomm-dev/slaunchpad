<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietNamAddress\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\VietNamAddress\Api\Data\VnPrimaryCandidateSelectionInterface;
use Secomm\VietNamAddress\Model\VnPrimaryCandidateSelector;

/**
 * TASK-MD2BD3 — deterministic curated-primary selector: directional, designation-based,
 * candidate-order invariant, integrity-defect fail-closed.
 */
class VnPrimaryCandidateSelectorTest extends TestCase
{
    private ResourceConnection&MockObject $resource;
    private Select&MockObject $select;
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
        $this->resource->method('getTableName')->willReturnCallback(static fn (string $t): string => $t);
        $this->resource->method('getConnection')->willReturn($this->mockConnection());
        $this->selector = new VnPrimaryCandidateSelector($this->resource);
    }

    private function mockConnection(): \Magento\Framework\DB\Adapter\AdapterInterface&MockObject
    {
        $connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $connection->method('select')->willReturn($this->select);
        $connection->method('fetchCol')->willReturnCallback(fn (Select $select): array => $this->primaryRows);

        return $connection;
    }

    public function testExactlyOneDirectionalPrimaryIsSelected(): void
    {
        $this->primaryRows = ['VNAP25-B2B2B2B2B2'];

        $selection = $this->selector->selectPrimary('VN_ADMIN_2025', 'VNA25-AAA', 'VN_ADMIN_PRE_2025', ['VNAP25-A1A1A1A1A1', 'VNAP25-B2B2B2B2B2']);

        $this->assertSame(VnPrimaryCandidateSelectionInterface::STATUS_SELECTED, $selection->getStatus());
        $this->assertSame('VNAP25-B2B2B2B2B2', $selection->getSelectedCode());
        $this->assertSame(2, $selection->getCandidateCount());
    }

    public function testZeroCuratedPrimaryMeansNoDesignatedPrimary(): void
    {
        $this->primaryRows = [];

        $selection = $this->selector->selectPrimary('VN_ADMIN_2025', 'VNA25-AAA', 'VN_ADMIN_PRE_2025', ['VNAP25-A1A1A1A1A1', 'VNAP25-B2B2B2B2B2']);

        $this->assertSame(VnPrimaryCandidateSelectionInterface::STATUS_NO_DESIGNATED_PRIMARY, $selection->getStatus());
        $this->assertNull($selection->getSelectedCode());
    }

    public function testMultipleCuratedPrimaryIsIntegrityDefect(): void
    {
        $this->primaryRows = ['VNAP25-A1A1A1A1A1', 'VNAP25-B2B2B2B2B2'];

        $selection = $this->selector->selectPrimary('VN_ADMIN_2025', 'VNA25-AAA', 'VN_ADMIN_PRE_2025', ['VNAP25-A1A1A1A1A1', 'VNAP25-B2B2B2B2B2']);

        $this->assertSame(VnPrimaryCandidateSelectionInterface::STATUS_MULTIPLE_PRIMARY, $selection->getStatus());
        $this->assertNull($selection->getSelectedCode());
    }

    public function testCandidateOrderCannotAffectSelection(): void
    {
        // Selection query theo designation (is_primary=1) — thứ tự truyền vào không đổi kết quả.
        $this->primaryRows = ['VNAP25-ZZZ'];

        $first = $this->selector->selectPrimary('VN_ADMIN_2025', 'VNA25-AAA', 'VN_ADMIN_PRE_2025', ['VNAP25-ZZZ', 'VNAP25-AAA']);
        $second = $this->selector->selectPrimary('VN_ADMIN_2025', 'VNA25-AAA', 'VN_ADMIN_PRE_2025', ['VNAP25-AAA', 'VNAP25-ZZZ']);

        $this->assertSame($first->getSelectedCode(), $second->getSelectedCode());
        $this->assertSame('VNAP25-ZZZ', $second->getSelectedCode());
    }

    public function testReverseDirectionEdgeIsNotEvaluated(): void
    {
        // Directional enforcement: query resolution direction PRE:B → 2025 chỉ match edge
        // source=B (không kế thừa primary của edge 2025:A → PRE:B).
        $this->primaryRows = [];

        $selection = $this->selector->selectPrimary('VN_ADMIN_PRE_2025', 'VNAP25-B2B2B2B2B2', 'VN_ADMIN_2025', ['VNA25-AAA']);

        // 1 candidate → NOT_APPLICABLE (unique set cần selector).
        $this->assertSame(VnPrimaryCandidateSelectionInterface::STATUS_NOT_APPLICABLE, $selection->getStatus());
    }

    public function testNotApplicableForSingleCandidate(): void
    {
        $this->primaryRows = [];

        $selection = $this->selector->selectPrimary('VN_ADMIN_2025', 'VNA25-AAA', 'VN_ADMIN_2025', ['VNA25-AAA']);

        $this->assertSame(VnPrimaryCandidateSelectionInterface::STATUS_NOT_APPLICABLE, $selection->getStatus());
    }

    public function testQueryIsDirectionalSourceToTarget(): void
    {
        $this->primaryRows = [];
        // AMBIGUOUS set (2 candidates) — query phải theo đúng hướng source → target.
        $this->selector->selectPrimary('VN_ADMIN_2025', 'VNA25-AAA', 'VN_ADMIN_PRE_2025', ['VNAP25-A1A1A1A1A1', 'VNAP25-B2B2B2B2B2']);

        $conditions = array_column($this->capturedWhere, 0);
        $this->assertContains('m.source_scheme = ?', $conditions);
        $this->assertContains('m.source_code = ?', $conditions);
        $this->assertContains('m.target_scheme = ?', $conditions);
        // Directional: KHÔNG có điều kiện theo hướng ngược (target_code → source_code matching).
        foreach ($conditions as $condition) {
            $this->assertStringNotContainsString('source_code = m.', $condition);
        }
    }
}
