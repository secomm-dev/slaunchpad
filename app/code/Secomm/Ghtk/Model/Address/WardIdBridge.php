<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Address;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Path B bridge (DEC-020): resolve the stable ward identifier for the VN 2-level
 * model. In the 2-level model the "ward" (phường/xã) IS the city level, so the
 * canonical ward_id = directory_region_city.city_id. This bridge recovers it from
 * a legacy payload that only carries the ward name string.
 *
 * Carrier-internal — does NOT modify the generic address data layer. Deterministic
 * within (region_id, default_name); logs a warning when a name matches more than
 * one ward (city) in a region.
 */
class WardIdBridge
{
    public function __construct(
        private ResourceConnection $resourceConnection,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @return int|null city_id (= ward_id in the 2-level model), or null when not found.
     */
    public function resolveWardId(int $regionId, string $wardName): ?int
    {
        $conn = $this->resourceConnection->getConnection();
        $cityTable = $this->resourceConnection->getTableName('directory_region_city');

        $select = $conn->select()
            ->from($cityTable, ['city_id'])
            ->where('region_id = ?', $regionId)
            ->where('default_name = ?', $wardName);

        $ids = $conn->fetchCol($select);

        if (empty($ids)) {
            return null;
        }

        if (count($ids) > 1) {
            $this->logger->warning(
                'GHTK WardIdBridge: ward name matches multiple cities in region; '
                . 'using the first match as a best-effort (path B). '
                . 'Consider exposing city_id at the address layer (DEC-020 path A).',
                ['region_id' => $regionId, 'ward_name' => $wardName, 'match_count' => count($ids)]
            );
        }

        return (int) $ids[0];
    }
}
