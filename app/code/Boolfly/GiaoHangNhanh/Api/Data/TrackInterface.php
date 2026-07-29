<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Boolfly\GiaoHangNhanh\Api\Data;

interface TrackInterface
{
    /**
     * String constants for property names
     */
    public const ENTITY_ID = "entity_id";
    public const ORDER_ID = "order_id";
    public const TRACKING_CODE = "tracking_code";
    public const STATUS_CODE = "status_code";
    public const STATUS_LABEL = "status_label";
    public const WAREHOUSE = "warehouse";
    public const TOTAL_FEE = "total_fee";
    public const SHOP_ID = "shop_id";
    public const REQUEST = "request";
    public const RESULT_CODE = "result_code";
    public const RESULT_LABEL = "result_label";
    public const ADDITIONAL_DATA = "additional_data";
    public const CREATED_AT = "created_at";
    public const UPDATED_AT = "updated_at";
    public const RESULT_CODE_SUCCESS = 1;
    public const RESULT_CODE_ERROR = 0;
    /**
     * Getter for EntityId.
     *
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * Setter for EntityId.
     *
     * @param int|null $entityId
     *
     * @return void
     */
    public function setEntityId(?int $entityId): void;

    /**
     * Getter for OrderId.
     *
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * Setter for OrderId.
     *
     * @param int|null $orderId
     *
     * @return void
     */
    public function setOrderId(?int $orderId): void;

    /**
     * Getter for TrackingCode.
     *
     * @return string|null
     */
    public function getTrackingCode(): ?string;

    /**
     * Setter for TrackingCode.
     *
     * @param string|null $trackingCode
     *
     * @return void
     */
    public function setTrackingCode(?string $trackingCode): void;

    /**
     * Getter for StatusCode.
     *
     * @return string|null
     */
    public function getStatusCode(): ?string;

    /**
     * Setter for StatusCode.
     *
     * @param string|null $statusCode
     *
     * @return void
     */
    public function setStatusCode(?string $statusCode): void;

    /**
     * Getter for StatusLabel.
     *
     * @return string|null
     */
    public function getStatusLabel(): ?string;

    /**
     * Setter for StatusLabel.
     *
     * @param string|null $statusLabel
     *
     * @return void
     */
    public function setStatusLabel(?string $statusLabel): void;

    /**
     * Getter for Warehouse.
     *
     * @return string|null
     */
    public function getWarehouse(): ?string;

    /**
     * Setter for Warehouse.
     *
     * @param string|null $warehouse
     *
     * @return void
     */
    public function setWarehouse(?string $warehouse): void;

    /**
     * Getter for TotalFee.
     *
     * @return float|null
     */
    public function getTotalFee(): ?float;

    /**
     * Setter for TotalFee.
     *
     * @param float|null $totalFee
     *
     * @return void
     */
    public function setTotalFee(?float $totalFee): void;

    /**
     * Getter for ShopId.
     *
     * @return int|null
     */
    public function getShopId(): ?int;

    /**
     * Setter for ShopId.
     *
     * @param int|null $shopId
     *
     * @return void
     */
    public function setShopId(?int $shopId): void;

    /**
     * Getter for Request.
     *
     * @return string|null
     */
    public function getRequest(): ?string;

    /**
     * Setter for Request.
     *
     * @param string|null $request
     *
     * @return void
     */
    public function setRequest(?string $request): void;

    /**
     * Getter for ResultCode.
     *
     * @return int|null
     */
    public function getResultCode(): ?int;

    /**
     * Setter for ResultCode.
     *
     * @param int|null $resultCode
     *
     * @return void
     */
    public function setResultCode(?int $resultCode): void;

    /**
     * Getter for ResultLabel.
     *
     * @return string|null
     */
    public function getResultLabel(): ?string;

    /**
     * Setter for ResultLabel.
     *
     * @param string|null $resultLabel
     *
     * @return void
     */
    public function setResultLabel(?string $resultLabel): void;

    /**
     * Getter for AdditionalData.
     *
     * @return string|null
     */
    public function getAdditionalData(): ?string;

    /**
     * Setter for AdditionalData.
     *
     * @param string|null $additionalData
     *
     * @return void
     */
    public function setAdditionalData(?string $additionalData): void;

    /**
     * Getter for CreatedAt.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Setter for CreatedAt.
     *
     * @param string|null $createdAt
     *
     * @return void
     */
    public function setCreatedAt(?string $createdAt): void;

    /**
     * Getter for UpdatedAt.
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Setter for UpdatedAt.
     *
     * @param string|null $updatedAt
     *
     * @return void
     */
    public function setUpdatedAt(?string $updatedAt): void;
}
