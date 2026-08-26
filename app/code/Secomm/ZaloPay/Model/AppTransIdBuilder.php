<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Model;

use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Builds the ZaloPay app_trans_id merchant reference.
 *
 * Format: "{ymd}_{timestamp_ms}_{reservedOrderId}" — identical to what
 * ZaloAppInfoDataBuilder has always produced, extracted so the
 * payment-first initiation flow can mint and persist the reference BEFORE
 * the provider call (it is the unique key that ties provider transaction,
 * attempt row and callback lookups together).
 */
class AppTransIdBuilder
{
    /**
     * AppTransIdBuilder constructor.
     *
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @param string $reservedOrderId
     * @return string
     */
    public function build(string $reservedOrderId): string
    {
        return $this->dateTime->gmtDate('ymd') . '_' . ($this->dateTime->timestamp() * 1000) . '_' . $reservedOrderId;
    }

    /**
     * App time in milliseconds (the ZaloPay v2/create app_time field).
     *
     * @return int
     */
    public function getAppTime(): int
    {
        return $this->dateTime->timestamp() * 1000;
    }
}
