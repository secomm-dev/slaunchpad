<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Api\Data;

/**
 * FEAT-CSWYEJ — pending-payment lifecycle record (DEC D2: standalone table).
 * One record per order; expires_at is an immutable snapshot taken at place order.
 */
interface PaymentInterface
{
    public const ENTITY_ID = 'entity_id';
    public const ORDER_ID = 'order_id';
    public const STORE_ID = 'store_id';
    public const METHOD_CODE = 'method_code';
    public const EXPIRES_AT = 'expires_at';
    public const STATUS = 'status';
    public const RETRY_COUNT = 'retry_count';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    public const RESOLVED_AT = 'resolved_at';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_ERROR = 'error';

    public function getEntityId(): ?int;

    public function setEntityId(?int $entityId): self;

    public function getOrderId(): int;

    public function setOrderId(int $orderId): self;

    public function getStoreId(): int;

    public function setStoreId(int $storeId): self;

    public function getMethodCode(): string;

    public function setMethodCode(string $methodCode): self;

    public function getExpiresAt(): string;

    public function setExpiresAt(string $expiresAt): self;

    public function getStatus(): string;

    public function setStatus(string $status): self;

    public function getRetryCount(): int;

    public function setRetryCount(int $count): self;

    public function getCreatedAt(): ?string;

    public function setCreatedAt(?string $createdAt): self;

    public function getUpdatedAt(): ?string;

    public function setUpdatedAt(?string $updatedAt): self;

    public function getResolvedAt(): ?string;

    public function setResolvedAt(?string $resolvedAt): self;
}
