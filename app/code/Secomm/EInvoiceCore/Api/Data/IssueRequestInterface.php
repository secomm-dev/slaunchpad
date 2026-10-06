<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Api\Data;

/**
 * Canonical invoice issue request from Magento order.
 *
 * @api
 * @since 1.0.0
 */
interface IssueRequestInterface
{
    public const ORDER_ID = 'order_id';
    public const ORDER_INCREMENT_ID = 'order_increment_id';
    public const STORE_ID = 'store_id';
    public const PAYLOAD = 'payload';

    /**
     * Return Magento order entity ID.
     *
     * @return int
     */
    public function getOrderId(): int;

    /**
     * Set Magento order entity ID.
     *
     * @param int $orderId
     * @return $this
     */
    public function setOrderId(int $orderId): self;

    /**
     * Return order increment ID (human-readable order number).
     *
     * @return string
     */
    public function getOrderIncrementId(): string;

    /**
     * Set order increment ID.
     *
     * @param string $incrementId
     * @return $this
     */
    public function setOrderIncrementId(string $incrementId): self;

    /**
     * Return store scope ID for the order.
     *
     * @return int
     */
    public function getStoreId(): int;

    /**
     * Set store scope ID.
     *
     * @param int $storeId
     * @return $this
     */
    public function setStoreId(int $storeId): self;

    /**
     * Provider-specific payload (MeInvoice Integration API InvoiceData).
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array;

    /**
     * Set provider-specific payload.
     *
     * @param array $payload
     * @return $this
     */
    public function setPayload(array $payload): self;
}
