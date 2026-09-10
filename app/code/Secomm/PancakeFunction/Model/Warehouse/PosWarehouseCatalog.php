<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PancakeFunction\Model\Warehouse;

use Secomm\PancakeFunction\Api\PosApiConfigInterface;
use Secomm\PancakeFunction\Model\Client\PosClient;
use Secomm\PancakeFunction\Model\Client\PosClientException;

/**
 * List Pancake POS warehouses for admin mapping dropdowns.
 */
class PosWarehouseCatalog
{
    public function __construct(
        private readonly PosClient $posClient,
        private readonly PosApiConfigInterface $config
    ) {
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function getWarehouses(?int $storeId = null): array
    {
        if (!$this->config->isEnabled($storeId)
            || $this->config->getShopId($storeId) === ''
            || $this->config->getApiKey($storeId) === ''
        ) {
            return [];
        }

        try {
            $response = $this->posClient->listWarehouses($storeId);
        } catch (PosClientException) {
            return [];
        }

        $rows = $response['data'] ?? $response;
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['id'])) {
                continue;
            }
            $out[] = [
                'id' => (string) $row['id'],
                'name' => (string) ($row['name'] ?? $row['id']),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string> id => label
     */
    public function getOptions(?int $storeId = null): array
    {
        $options = [];
        foreach ($this->getWarehouses($storeId) as $row) {
            $options[$row['id']] = $row['name'] . ' (' . $row['id'] . ')';
        }
        return $options;
    }
}
