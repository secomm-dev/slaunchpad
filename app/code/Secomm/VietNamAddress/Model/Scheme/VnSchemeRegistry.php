<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Scheme;

use Magento\Framework\App\ResourceConnection;

/**
 * DEC-FEATYA2C0W-003 / TASK-9394A9 — read access to the scheme registry
 * (secomm_vietnam_address_scheme). Status (CURRENT/HISTORICAL/FUTURE) is a label
 * maintained by SchemeRegistryUpdater; the scheme_code is the immutable identity.
 */
class VnSchemeRegistry
{
    public const TABLE = 'secomm_vietnam_address_scheme';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @return array{scheme_code: string, label: string, profile_code: string, level_count: int, status: string, effective_from: string|null, effective_to: string|null}|null
     */
    public function get(string $schemeCode): ?array
    {
        $row = $this->connection()->fetchRow(
            $this->connection()->select()
                ->from($this->table(self::TABLE))
                ->where('scheme_code = ?', $schemeCode)
        );

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * The scheme whose dataset is installed right now (status CURRENT), null when none.
     */
    public function getCurrent(): ?string
    {
        $schemeCode = $this->connection()->fetchOne(
            $this->connection()->select()
                ->from($this->table(self::TABLE), ['scheme_code'])
                ->where('status = ?', VnSchemes::STATUS_CURRENT)
                ->limit(1)
        );

        return $schemeCode === false || $schemeCode === '' ? null : (string)$schemeCode;
    }

    /**
     * @return array<int, array{scheme_code: string, label: string, profile_code: string, level_count: int, status: string, effective_from: string|null, effective_to: string|null}>
     */
    public function getAll(): array
    {
        if (!$this->connection()->isTableExists($this->table(self::TABLE))) {
            return [];
        }

        return array_map(
            fn (array $row): array => $this->hydrate($row),
            $this->connection()->fetchAll(
                $this->connection()->select()
                    ->from($this->table(self::TABLE))
                    ->order('scheme_code ASC')
            )
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array{scheme_code: string, label: string, profile_code: string, level_count: int, status: string, effective_from: string|null, effective_to: string|null}
     */
    private function hydrate(array $row): array
    {
        return [
            'scheme_code' => (string)$row['scheme_code'],
            'label' => (string)$row['label'],
            'profile_code' => (string)$row['profile_code'],
            'level_count' => (int)$row['level_count'],
            'status' => (string)$row['status'],
            'effective_from' => $row['effective_from'] !== null ? (string)$row['effective_from'] : null,
            'effective_to' => $row['effective_to'] !== null ? (string)$row['effective_to'] : null,
        ];
    }

    private function connection(): \Magento\Framework\DB\Adapter\AdapterInterface
    {
        return $this->resource->getConnection();
    }

    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }
}
