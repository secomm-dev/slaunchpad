<?php
/**
 * Builds the MoMo merchant references (orderId + requestId) for an attempt.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Model;

use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Mints and persists the MoMo `orderId` (order_ref) + `requestId` BEFORE
 * the provider create call — the unique keys that tie the provider
 * transaction, the attempt row and every callback lookup together.
 *
 * Format: order_ref = "MOMO{ymdHis}{reservedOrderId}{4hex}" (<= 64 chars),
 * requestId = "{orderRef}-R{4hex}". Both are unique per attempt; the
 * attempt table's unique constraints are the hard duplicate guard.
 */
class OrderRefBuilder
{
    /**
     * OrderRefBuilder constructor.
     *
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * Build the MoMo orderId merchant reference for a quote's reserved order id.
     *
     * @param string $reservedOrderId
     * @return string
     */
    public function buildOrderRef(string $reservedOrderId): string
    {
        return 'MOMO' . $this->dateTime->gmtDate('ymdHis') . $reservedOrderId . $this->randomSuffix();
    }

    /**
     * Build the MoMo requestId identity for an order ref.
     *
     * @param string $orderRef
     * @return string
     */
    public function buildRequestId(string $orderRef): string
    {
        return $orderRef . '-R' . $this->randomSuffix();
    }

    /**
     * Short random suffix (collision safety within one second clock tick).
     *
     * @return string 4 hex chars.
     */
    private function randomSuffix(): string
    {
        return substr(bin2hex(random_bytes(2)), 0, 4);
    }
}
