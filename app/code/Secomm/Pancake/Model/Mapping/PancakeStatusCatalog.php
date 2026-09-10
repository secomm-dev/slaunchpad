<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Model\Mapping;

use Magento\Sales\Model\Order;

/**
 * Static catalog of Pancake order status codes for admin Status Mapping options.
 * Same role as PosWarehouseCatalog for warehouse mapping (not a live POS API).
 */
class PancakeStatusCatalog
{
    /**
     * @return array<string, string> code => admin label
     */
    public function getOptions(): array
    {
        $labels = [];
        foreach ($this->getDefaultMaps() as $row) {
            $labels[$row['code']] = $row['label'];
        }

        return $labels;
    }

    /**
     * Optional seed rows: Pancake code → Magento order status (admin can edit later).
     *
     * @return list<array{code: string, label: string, magento_status: string}>
     */
    public function getDefaultMaps(): array
    {
        return [
            ['code' => '0', 'label' => 'New', 'magento_status' => Order::STATE_PROCESSING],
            ['code' => '1', 'label' => 'Confirmed', 'magento_status' => Order::STATE_PROCESSING],
            ['code' => '11', 'label' => 'Waiting confirmation', 'magento_status' => Order::STATE_PROCESSING],
            ['code' => '17', 'label' => 'Partially returned / wait', 'magento_status' => Order::STATE_PROCESSING],
            ['code' => '20', 'label' => 'Wait print', 'magento_status' => Order::STATE_PROCESSING],
            ['code' => '8', 'label' => 'Packing', 'magento_status' => Order::STATE_PROCESSING],
            ['code' => '12', 'label' => 'Packing (alt)', 'magento_status' => Order::STATE_PROCESSING],
            ['code' => '13', 'label' => 'Packed', 'magento_status' => Order::STATE_PROCESSING],
            ['code' => '9', 'label' => 'Waiting pickup', 'magento_status' => Order::STATE_PROCESSING],
            ['code' => '2', 'label' => 'Shipped', 'magento_status' => Order::STATE_PROCESSING],
            ['code' => '3', 'label' => 'Delivered', 'magento_status' => Order::STATE_COMPLETE],
            ['code' => '16', 'label' => 'Delivered (alt)', 'magento_status' => Order::STATE_COMPLETE],
            ['code' => '4', 'label' => 'Returning', 'magento_status' => Order::STATE_PROCESSING],
            ['code' => '15', 'label' => 'Returning (alt)', 'magento_status' => Order::STATE_PROCESSING],
            ['code' => '5', 'label' => 'Returned', 'magento_status' => Order::STATE_CLOSED],
            ['code' => '6', 'label' => 'Cancelled', 'magento_status' => Order::STATE_CANCELED],
            ['code' => '7', 'label' => 'Cancelled (alt)', 'magento_status' => Order::STATE_CANCELED],
        ];
    }
}
