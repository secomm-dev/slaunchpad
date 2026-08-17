<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Address;

use Magento\Framework\App\ResourceConnection;

/**
 * Best-effort vi_VN fallback (DEC-020). Reads the localized VN address names
 * directly from the tables owned by Secomm_AddressDropdown — read-only, no
 * generic-module modification.
 *
 * VN 2-level model: province = directory_country_region; ward (phường/xã) =
 * directory_region_city. So the ward name is read from directory_region_city_name
 * (by city_id), NOT from the deprecated sub-city (3rd) level.
 *
 * This builds a *valid* request payload; it does NOT guarantee GHTK recognises
 * the address. The caller (SL-009 rate collection) must handle rejection gracefully.
 */
class BestEffortViVnResolver
{
    private const LOCALE_VI_VN = 'vi_VN';

    public function __construct(
        private ResourceConnection $resourceConnection
    ) {
    }

    /**
     * vi_VN province name for a region_id, or null when no localised row exists.
     */
    public function getProvinceName(int $regionId): ?string
    {
        $conn = $this->resourceConnection->getConnection();
        $select = $conn->select()
            ->from($this->resourceConnection->getTableName('directory_country_region_name'), ['name'])
            ->where('region_id = ?', $regionId)
            ->where('locale = ?', self::LOCALE_VI_VN)
            ->limit(1);

        $name = $conn->fetchOne($select);

        return $name !== false && $name !== '' ? (string) $name : null;
    }

    /**
     * vi_VN ward (city-level) name for a city_id (= ward_id in the 2-level model),
     * falling back to the default_name when no localised row exists. Returns null
     * only when the city itself is unknown.
     */
    public function getWardName(int $wardId): ?string
    {
        $conn = $this->resourceConnection->getConnection();

        // Prefer the vi_VN localised name.
        $select = $conn->select()
            ->from($this->resourceConnection->getTableName('directory_region_city_name'), ['name'])
            ->where('city_id = ?', $wardId)
            ->where('locale = ?', self::LOCALE_VI_VN)
            ->limit(1);
        $name = $conn->fetchOne($select);

        if ($name !== false && $name !== '') {
            return (string) $name;
        }

        // Fall back to the default name.
        $select = $conn->select()
            ->from($this->resourceConnection->getTableName('directory_region_city'), ['default_name'])
            ->where('city_id = ?', $wardId)
            ->limit(1);
        $default = $conn->fetchOne($select);

        return $default !== false && $default !== '' ? (string) $default : null;
    }
}
