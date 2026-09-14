<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Api;

use Magento\Sales\Api\Data\OrderInterface;
use Secomm\FulfillmentCore\Api\Data\ExportResultInterface;

/**
 * Adapter-implemented exporter. Core never hard-codes vendor APIs.
 */
interface OrderExporterInterface
{
    /**
     * Stable service key used in mapping rows and DI pool item names.
     */
    public function getServiceCode(): string;

    /**
     * Whether this adapter should run for the order store.
     *
     * @param int|null $storeId Store scope; null uses default config scope
     */
    public function isEnabled(?int $storeId = null): bool;

    /**
     * Push order details to the remote OMS. Must not throw for business failures.
     *
     * @param OrderInterface $order Magento sales order to push
     * @return ExportResultInterface Success flag, remote id, generic error code
     */
    public function export(OrderInterface $order): ExportResultInterface;
}
