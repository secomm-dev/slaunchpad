<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Address\Sync;

use Magento\Framework\Exception\LocalizedException;
use Secomm\Ghn\Model\Address\GhnSchemes;
use Secomm\Ghn\Api\Client\GhnApiClientInterface;
use Secomm\Ghn\Model\Client\GhnEndpoints;

/**
 * Legacy administrative master data (province → district → ward) — GHN_ADMIN_PRE_2025.
 * Verified contract: GET master-data/province (no params), master-data/district?province_id,
 * master-data/ward?district_id; fields ProvinceID/ProvinceName, DistrictID/DistrictName,
 * WardCode/WardName (developer.ghn.vn "Get Province" + legacy Secomm_GiaoHangNhanh CLI usage).
 * The legacy model has no unit status field — everything fetched is ACTIVE.
 */
class Pre2025MasterDataFetcher implements MasterDataFetcher
{
    public function __construct(private readonly GhnApiClientInterface $client)
    {
    }

    public function supports(): string
    {
        return GhnSchemes::GHN_ADMIN_PRE_2025;
    }

    public function fetch(string $scheme): array
    {
        GhnSchemes::assertKnown($scheme);
        if ($scheme !== $this->supports()) {
            throw new LocalizedException(__('Fetcher %1 cannot serve scheme %2.', self::class, $scheme));
        }

        $rows = [];
        $provinces = $this->requireList(
            $this->client->get('legacy_master_data_province', GhnEndpoints::MASTER_DATA_PROVINCES)
        );

        foreach ($provinces as $province) {
            $provinceId = $this->scalarKey($province, 'ProvinceID');
            // TASK-6TNKDH real-data finding: GHN legacy id namespaces are PER LEVEL — ProvinceID
            // 2002 ("Hà Nội 02", sandbox test row) collides with DistrictID 2002 ("Huyện Quảng
            // Ninh"). The portable provider_key therefore carries a level namespace prefix and
            // the full parent chain; provider_id/provider_code keep the RAW GHN contract values.
            $provinceKey = 'p:' . $provinceId;
            $rows[] = $this->row(
                key: $provinceKey,
                providerId: $provinceId,
                depth: 1,
                name: $this->scalarName($province, 'ProvinceName')
            );

            foreach ($this->fetchChildren(GhnEndpoints::MASTER_DATA_DISTRICTS, 'legacy_master_data_district', 'province_id', $provinceId) as $district) {
                $districtId = $this->scalarKey($district, 'DistrictID');
                $districtKey = 'd:' . $provinceId . ':' . $districtId;
                $rows[] = $this->row(
                    key: $districtKey,
                    providerId: $districtId,
                    depth: 2,
                    name: $this->scalarName($district, 'DistrictName'),
                    parentKey: $provinceKey
                );

                foreach ($this->fetchChildren(GhnEndpoints::MASTER_DATA_WARDS, 'legacy_master_data_ward', 'district_id', $districtId) as $ward) {
                    $wardCode = $this->scalarKey($ward, 'WardCode');
                    $rows[] = $this->row(
                        // WardCode is unique only within its district — the ward key is
                        // district-scoped; provider_code keeps the raw WardCode (API value).
                        key: 'w:' . $districtId . ':' . $wardCode,
                        providerId: null,
                        providerCode: $wardCode,
                        depth: 3,
                        name: $this->scalarName($ward, 'WardName'),
                        parentKey: $districtKey
                    );
                }
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchChildren(string $path, string $operation, string $paramName, string $parentKey): array
    {
        return $this->requireList(
            $this->client->get($operation, $path, [$paramName => $parentKey])
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function requireList(mixed $data): array
    {
        if (!is_array($data) || array_is_list($data) === false) {
            throw new LocalizedException(__('GHN master data response is not a list.'));
        }

        return $data;
    }

    private function scalarKey(array $item, string $field): string
    {
        $value = $item[$field] ?? null;
        if ($value === null || (is_scalar($value) === false)) {
            throw new LocalizedException(__('GHN master data item is missing field "%1".', $field));
        }

        return (string) $value;
    }

    private function scalarName(array $item, string $field): string
    {
        $value = $item[$field] ?? '';
        if (!is_string($value) && !is_numeric($value)) {
            throw new LocalizedException(__('GHN master data item is missing field "%1".', $field));
        }

        return trim((string) $value);
    }

    private function row(
        string $key,
        ?string $providerId,
        int $depth,
        string $name,
        ?string $parentKey = null,
        ?string $providerCode = null
    ): array {
        return [
            'provider_key' => $key,
            'provider_id' => $providerId,
            'provider_code' => $providerCode,
            'parent_key' => $parentKey,
            'depth' => $depth,
            'name' => $name,
            'extension_names' => null,
            'status' => GhnSchemes::STATUS_ACTIVE,
        ];
    }
}
