<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * TASK-J9AVGK — thin SQL collaborator of the resolver: the union of outgoing edges
 * (source side matches the input) and incoming edges (target side matches the input),
 * restricted to the requested scheme pair. One statement, parameterized, explicit columns.
 */
class MappingCandidateFinder
{
    private const TABLE = 'secomm_vietnam_address_mapping';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @return array<int, array{code: string, relation_type: string, direction: string}> direction: 'outgoing'|'incoming'
     */
    public function find(string $sourceScheme, string $sourceCode, string $targetScheme): array
    {
        $connection = $this->connection();
        $outgoing = $connection->select()
            ->from(
                ['m' => $this->table(self::TABLE)],
                [
                    'code' => 'm.target_code',
                    'relation_type' => 'm.relation_type',
                    'direction' => new \Zend_Db_Expr("'outgoing'"),
                ]
            )
            ->where('m.source_scheme = ?', $sourceScheme)
            ->where('m.source_code = ?', $sourceCode)
            ->where('m.target_scheme = ?', $targetScheme);

        $incoming = $connection->select()
            ->from(
                ['m' => $this->table(self::TABLE)],
                [
                    'code' => 'm.source_code',
                    'relation_type' => 'm.relation_type',
                    'direction' => new \Zend_Db_Expr("'incoming'"),
                ]
            )
            ->where('m.target_scheme = ?', $sourceScheme)
            ->where('m.target_code = ?', $sourceCode)
            ->where('m.source_scheme = ?', $targetScheme);

        // Magento union pattern: a fresh select carries BOTH parts.
        $union = $connection->select()->union([$outgoing, $incoming], \Magento\Framework\DB\Select::SQL_UNION_ALL);

        return $connection->fetchAll($union);
    }

    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }

    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }
}
