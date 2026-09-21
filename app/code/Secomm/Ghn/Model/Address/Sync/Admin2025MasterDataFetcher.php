<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Sync;

use Magento\Framework\Exception\LocalizedException;
use Secomm\Ghn\Api\Client\GhnApiClientInterface;
use Secomm\Ghn\Model\Address\GhnSchemes;
use Secomm\Ghn\Model\Client\GhnEndpoints;

/**
 * Current administrative master data (province → ward, 2-level) — GHN_ADMIN_2025.
 * Verified contract (developer.ghn.vn "Get Province (New)" / "Get Ward (New)", 2026-09-10):
 * GET v3/master-data/province/all (offset/limit, limit ≤ 200) and
 * GET v3/master-data/ward/all-by-province-id (province_id + paging).
 * data[] fields: `_id`, `name` (verbatim Create Order value), `extension_names`, `type`,
 * `parent_id`, `status` (1=active, 2=disabled, 10=deleted — deleted rows are SKIPPED entirely,
 * disabled rows are stored DISABLED).
 */
class Admin2025MasterDataFetcher implements MasterDataFetcher
{
    /** GHN v3 status values. */
    private const STATUS_ACTIVE = 1;
    private const STATUS_DISABLED = 2;
    private const STATUS_DELETED = 10;

    public function __construct(
        private readonly GhnApiClientInterface $client,
        private readonly int $pageSize = GhnEndpoints::V3_PAGE_SIZE
    ) {
    }

    public function supports(): string
    {
        return GhnSchemes::GHN_ADMIN_2025;
    }

    public function fetch(string $scheme): array
    {
        GhnSchemes::assertKnown($scheme);
        if ($scheme !== $this->supports()) {
            throw new LocalizedException(__('Fetcher %1 cannot serve scheme %2.', self::class, $scheme));
        }

        $provinceRows = [];
        $wardRows = [];
        foreach ($this->fetchPaged(GhnEndpoints::MASTER_DATA_PROVINCES_V3, 'new_master_data_province', []) as $province) {
            $provinceId = $this->id($province);
            $status = $this->ghnStatus($province);
            if ($status === self::STATUS_DELETED) {
                continue;
            }

            $provinceRows[] = [
                'provider_key' => $provinceId,
                'provider_id' => $provinceId,
                'provider_code' => null,
                'parent_key' => null,
                'depth' => 1,
                'name' => $this->name($province),
                'extension_names' => $this->extensionNames($province),
                'status' => $status === self::STATUS_ACTIVE ? GhnSchemes::STATUS_ACTIVE : GhnSchemes::STATUS_DISABLED,
            ];

            if ($status !== self::STATUS_ACTIVE) {
                continue;
            }

            foreach ($this->fetchPaged(
                GhnEndpoints::MASTER_DATA_WARDS_BY_PROVINCE_V3,
                'new_master_data_ward',
                ['province_id' => $provinceId]
            ) as $ward) {
                if ($this->ghnStatus($ward) === self::STATUS_DELETED) {
                    continue;
                }

                $wardRows[] = [
                    'provider_key' => $this->id($ward),
                    'provider_id' => $this->id($ward),
                    'provider_code' => null,
                    'parent_key' => $provinceId,
                    'depth' => 2,
                    'name' => $this->name($ward),
                    'extension_names' => $this->extensionNames($ward),
                    'status' => $this->ghnStatus($ward) === self::STATUS_ACTIVE
                        ? GhnSchemes::STATUS_ACTIVE
                        : GhnSchemes::STATUS_DISABLED,
                ];
            }
        }

        // Depth-ASC parent-first output (MasterDataFetcher contract) — provinces before wards.
        return array_merge($provinceRows, $wardRows);
    }

    /**
     * Paged GET against a v3 endpoint (limit ≤ GhnEndpoints::V3_PAGE_SIZE per page).
     *
     * @param array<string, string|int> $baseParams
     * @return array<int, array<string, mixed>>
     */
    private function fetchPaged(string $path, string $operation, array $baseParams): array
    {
        $items = [];
        $offset = 0;

        do {
            $page = $this->client->get(
                $operation,
                $path,
                $baseParams + ['offset' => $offset, 'limit' => $this->pageSize]
            );

            if (!is_array($page) || array_is_list($page) === false) {
                throw new LocalizedException(__('GHN v3 master data response is not a list.'));
            }

            $count = count($page);
            foreach ($page as $item) {
                $items[] = $item;
            }
            $offset += $count;
        } while ($count === $this->pageSize);

        return $items;
    }

    private function id(array $item): string
    {
        $value = $item['_id'] ?? null;
        if ($value === null || is_scalar($value) === false) {
            throw new LocalizedException(__('GHN v3 master data item is missing field "_id".'));
        }

        return (string) $value;
    }

    private function name(array $item): string
    {
        $value = $item['name'] ?? '';
        if (!is_string($value) || trim($value) === '') {
            throw new LocalizedException(__('GHN v3 master data item is missing field "name".'));
        }

        return trim($value);
    }

    /**
     * @return string|null JSON array or null
     */
    private function extensionNames(array $item): ?string
    {
        $value = $item['extension_names'] ?? null;
        if (!is_array($value)) {
            return null;
        }

        $clean = array_values(array_filter($value, static fn ($v): bool => is_string($v) && trim($v) !== ''));

        return $clean === [] ? null : (string) json_encode($clean, JSON_UNESCAPED_UNICODE);
    }

    private function ghnStatus(array $item): int
    {
        return (int) ($item['status'] ?? self::STATUS_ACTIVE);
    }
}
