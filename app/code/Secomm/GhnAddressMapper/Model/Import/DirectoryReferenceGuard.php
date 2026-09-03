<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\GhnAddressMapper\Model\Import;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Secomm\VietNamAddress\Api\DirectoryReferenceGuardInterface;

/**
 * DEC-FEATYA2C0W-004 (D7) / TASK-Q4B98P — GHN-side registration of the VN scheme-swap
 * reference guard. The GHN mapping table keys on Magento directory PKs (region_id,
 * city_id); a VN scheme swap would orphan its rows, so every destructive VN scheme
 * operation must first confirm this table holds no references. The table knowledge lives
 * HERE (owning module) — Secomm_VietNamAddress orchestrates via DI without naming it.
 */
class DirectoryReferenceGuard implements DirectoryReferenceGuardInterface
{
    private const TABLE = 'secomm_ghn_address_mapping_location';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    public function getName(): string
    {
        return 'Secomm_GhnAddressMapper (' . self::TABLE . ')';
    }

    public function assertSafe(array $regionIds, array $cityIds): void
    {
        if ($regionIds === [] && $cityIds === []) {
            return;
        }
        $connection = $this->resource->getConnection();
        if (!$connection->isTableExists($this->resource->getTableName(self::TABLE))) {
            return;
        }

        foreach (['region_id' => $regionIds, 'city_id' => $cityIds] as $column => $ids) {
            if ($ids === []) {
                continue;
            }
            $references = (int)$connection->fetchOne(
                $connection->select()
                    ->from($this->resource->getTableName(self::TABLE), ['COUNT(*)'])
                    ->where($column . ' IN (?)', $ids)
            );
            if ($references > 0) {
                throw new LocalizedException(
                    __(
                        '%1 row(s) in %2 (column %3) still reference the installed VN data. '
                        . 'Clear that carrier mapping data first.',
                        $references,
                        self::TABLE,
                        $column
                    )
                );
            }
        }
    }
}
