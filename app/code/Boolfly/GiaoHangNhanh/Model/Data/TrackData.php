<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Boolfly\GiaoHangNhanh\Model\Data;

use Boolfly\GiaoHangNhanh\Api\Data\TrackInterface;
use Magento\Framework\DataObject;
/**
 * Class TrackData
 *
 * @package Boolfly\GiaoHangNhanh\Model\Data
 */
class TrackData extends DataObject implements TrackInterface
{
    /**
     * Getter for EntityId.
     *
     * @return int|null
     */
    public function getEntityId(): ?int
    {
        return $this->getData(self::ENTITY_ID) === null ? null
            : (int)$this->getData(self::ENTITY_ID);
    }

    /**
     * Setter for EntityId.
     *
     * @param int|null $entityId
     *
     * @return void
     */
    public function setEntityId(?int $entityId): void
    {
        $this->setData(self::ENTITY_ID, $entityId);
    }

    /**
     * Getter for OrderId.
     *
     * @return int|null
     */
    public function getOrderId(): ?int
    {
        return $this->getData(self::ORDER_ID) === null ? null
            : (int)$this->getData(self::ORDER_ID);
    }

    /**
     * Setter for OrderId.
     *
     * @param int|null $orderId
     *
     * @return void
     */
    public function setOrderId(?int $orderId): void
    {
        $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * Getter for TrackingCode.
     *
     * @return string|null
     */
    public function getTrackingCode(): ?string
    {
        return $this->getData(self::TRACKING_CODE);
    }

    /**
     * Setter for TrackingCode.
     *
     * @param string|null $trackingCode
     *
     * @return void
     */
    public function setTrackingCode(?string $trackingCode): void
    {
        $this->setData(self::TRACKING_CODE, $trackingCode);
    }

    /**
     * Getter for StatusCode.
     *
     * @return string|null
     */
    public function getStatusCode(): ?string
    {
        return $this->getData(self::STATUS_CODE);
    }

    /**
     * Setter for StatusCode.
     *
     * @param string|null $statusCode
     *
     * @return void
     */
    public function setStatusCode(?string $statusCode): void
    {
        $this->setData(self::STATUS_CODE, $statusCode);
    }

    /**
     * Getter for StatusLabel.
     *
     * @return string|null
     */
    public function getStatusLabel(): ?string
    {
        return $this->getData(self::STATUS_LABEL);
    }

    /**
     * Setter for StatusLabel.
     *
     * @param string|null $statusLabel
     *
     * @return void
     */
    public function setStatusLabel(?string $statusLabel): void
    {
        $this->setData(self::STATUS_LABEL, $statusLabel);
    }

    /**
     * Getter for Warehouse.
     *
     * @return string|null
     */
    public function getWarehouse(): ?string
    {
        return $this->getData(self::WAREHOUSE);
    }

    /**
     * Setter for Warehouse.
     *
     * @param string|null $warehouse
     *
     * @return void
     */
    public function setWarehouse(?string $warehouse): void
    {
        $this->setData(self::WAREHOUSE, $warehouse);
    }

    /**
     * Getter for TotalFee.
     *
     * @return float|null
     */
    public function getTotalFee(): ?float
    {
        return $this->getData(self::TOTAL_FEE) === null ? null
            : (float)$this->getData(self::TOTAL_FEE);
    }

    /**
     * Setter for TotalFee.
     *
     * @param float|null $totalFee
     *
     * @return void
     */
    public function setTotalFee(?float $totalFee): void
    {
        $this->setData(self::TOTAL_FEE, $totalFee);
    }

    /**
     * Getter for ShopId.
     *
     * @return int|null
     */
    public function getShopId(): ?int
    {
        return $this->getData(self::SHOP_ID) === null ? null
            : (int)$this->getData(self::SHOP_ID);
    }

    /**
     * Setter for ShopId.
     *
     * @param int|null $shopId
     *
     * @return void
     */
    public function setShopId(?int $shopId): void
    {
        $this->setData(self::SHOP_ID, $shopId);
    }

    /**
     * Getter for Request.
     *
     * @return string|null
     */
    public function getRequest(): ?string
    {
        return $this->getData(self::REQUEST);
    }

    /**
     * Setter for Request.
     *
     * @param string|null $request
     *
     * @return void
     */
    public function setRequest(?string $request): void
    {
        $this->setData(self::REQUEST, $request);
    }

    /**
     * Getter for ResultCode.
     *
     * @return int|null
     */
    public function getResultCode(): ?int
    {
        return $this->getData(self::RESULT_CODE) === null ? null
            : (int)$this->getData(self::RESULT_CODE);
    }

    /**
     * Setter for ResultCode.
     *
     * @param int|null $resultCode
     *
     * @return void
     */
    public function setResultCode(?int $resultCode): void
    {
        $this->setData(self::RESULT_CODE, $resultCode);
    }

    /**
     * Getter for ResultLabel.
     *
     * @return string|null
     */
    public function getResultLabel(): ?string
    {
        return $this->getData(self::RESULT_LABEL);
    }

    /**
     * Setter for ResultLabel.
     *
     * @param string|null $resultLabel
     *
     * @return void
     */
    public function setResultLabel(?string $resultLabel): void
    {
        $this->setData(self::RESULT_LABEL, $resultLabel);
    }

    /**
     * Getter for AdditionalData.
     *
     * @return string|null
     */
    public function getAdditionalData(): ?string
    {
        return $this->getData(self::ADDITIONAL_DATA);
    }

    /**
     * Setter for AdditionalData.
     *
     * @param string|null $additionalData
     *
     * @return void
     */
    public function setAdditionalData(?string $additionalData): void
    {
        $this->setData(self::ADDITIONAL_DATA, $additionalData);
    }

    /**
     * Getter for CreatedAt.
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * Setter for CreatedAt.
     *
     * @param string|null $createdAt
     *
     * @return void
     */
    public function setCreatedAt(?string $createdAt): void
    {
        $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * Getter for UpdatedAt.
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * Setter for UpdatedAt.
     *
     * @param string|null $updatedAt
     *
     * @return void
     */
    public function setUpdatedAt(?string $updatedAt): void
    {
        $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
