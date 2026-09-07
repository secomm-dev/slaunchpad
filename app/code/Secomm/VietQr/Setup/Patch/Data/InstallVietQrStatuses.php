<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietQr\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * Installs the two VietQR-owned order statuses on state `new`
 * (TASK-N35E28 / SPEC-FEAT-ZKD4VA §3 AC-007, AC-015):
 * - vietqr_pending: order placed, customer has not confirmed the transfer yet
 * - vietqr_awaiting_payment_confirm: customer confirmed the bank transfer
 * Custom statuses (not `pending`/`pending_payment`) so merchants can filter
 * VietQR orders in the Admin grid and customers see "unpaid" in My Orders.
 */
class InstallVietQrStatuses implements DataPatchInterface, PatchVersionInterface
{
    private const STATUSES = [
        ['status' => 'vietqr_pending', 'label' => 'Pending VietQR'],
        ['status' => 'vietqr_awaiting_payment_confirm', 'label' => 'Awaiting Payment Confirm'],
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    /**
     * @return array
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return void
     */
    public function apply(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $statusTable = $this->moduleDataSetup->getTable('sales_order_status');
        $stateTable = $this->moduleDataSetup->getTable('sales_order_status_state');

        foreach (self::STATUSES as $status) {
            $connection->insertForce(
                $statusTable,
                ['status' => $status['status'], 'label' => $status['label']]
            );
            $connection->insertForce(
                $stateTable,
                [
                    'status' => $status['status'],
                    'state' => 'new',
                    'is_default' => 0,
                    'visible_on_front' => 1,
                ]
            );
        }
    }

    /**
     * @return array
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @return string
     */
    public static function getVersion(): string
    {
        return '1.0.2';
    }
}
