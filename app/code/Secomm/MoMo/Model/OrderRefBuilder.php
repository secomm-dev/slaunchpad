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
     * Build the MoMo orderId for a REFUND transaction.
     *
     * MoMo requires the refund orderId to differ from the ORIGINAL purchase
     * orderId, and both orderId and requestId are String(50) at the provider
     * — hence the orderRef truncation (the recognizable prefix is kept, the
     * tail is random for uniqueness).
     *
     * @param string $orderRef Purchase order_ref (the original MoMo orderId).
     * @return string <= 49 chars.
     */
    public function buildRefundOrderId(string $orderRef): string
    {
        return substr($orderRef, 0, 42) . '-RF' . $this->randomSuffix();
    }

    /**
     * Build the MoMo requestId for a REFUND transaction (the provider
     * idempotency key — minted once per refund row, stored, never regenerated).
     *
     * @param string $orderRef Purchase order_ref (the original MoMo orderId).
     * @return string <= 49 chars.
     */
    public function buildRefundRequestId(string $orderRef): string
    {
        return substr($orderRef, 0, 42) . '-RQ' . $this->randomSuffix();
    }

    /**
     * Build the MoMo requestId for a REFUND QUERY call
     * (/v2/gateway/api/refund/query).
     *
     * The query is a DIFFERENT API operation than the refund submission, so
     * it must never reuse the refund submission's requestId (that is the
     * provider idempotency key of the refund itself): each resolve
     * invocation mints its own fresh query identity and signs with it. The
     * stored refund requestId stays immutable as refund-submission evidence.
     *
     * @param string $refundOrderId The refund's own orderId.
     * @return string <= 49 chars (provider requestId limit 50).
     */
    public function buildRefundQueryRequestId(string $refundOrderId): string
    {
        return substr($refundOrderId, 0, 42) . '-QQ' . $this->randomSuffix();
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
