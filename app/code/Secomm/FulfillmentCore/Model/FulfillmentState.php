<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\FulfillmentCore\Model;

use Magento\Framework\Model\AbstractModel;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentState as ResourceModel;

/**
 * Latest normalized fulfillment state per Magento order (SLP-30 FFC-002).
 */
class FulfillmentState extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }

    public function getMagentoOrderId(): int
    {
        return (int) $this->getData('magento_order_id');
    }

    public function setMagentoOrderId(int $id): self
    {
        return $this->setData('magento_order_id', $id);
    }

    public function getServiceCode(): string
    {
        return (string) $this->getData('service_code');
    }

    public function setServiceCode(string $code): self
    {
        return $this->setData('service_code', $code);
    }

    public function getExternalOrderId(): ?string
    {
        $value = $this->getData('external_order_id');
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setExternalOrderId(?string $externalOrderId): self
    {
        return $this->setData('external_order_id', $externalOrderId);
    }

    public function getNormalizedStatus(): string
    {
        return (string) $this->getData('normalized_status');
    }

    public function setNormalizedStatus(string $status): self
    {
        return $this->setData('normalized_status', $status);
    }

    public function getRawStatus(): ?string
    {
        $value = $this->getData('raw_status');
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setRawStatus(?string $rawStatus): self
    {
        return $this->setData('raw_status', $rawStatus);
    }

    public function getLastEventId(): ?string
    {
        $value = $this->getData('last_event_id');
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setLastEventId(?string $eventId): self
    {
        return $this->setData('last_event_id', $eventId);
    }

    public function getCarrierName(): ?string
    {
        $value = $this->getData('carrier_name');
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setCarrierName(?string $name): self
    {
        return $this->setData('carrier_name', $name);
    }

    public function getTrackingNumber(): ?string
    {
        $value = $this->getData('tracking_number');
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setTrackingNumber(?string $trackingNumber): self
    {
        return $this->setData('tracking_number', $trackingNumber);
    }

    public function getTrackingUrl(): ?string
    {
        $value = $this->getData('tracking_url');
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setTrackingUrl(?string $url): self
    {
        return $this->setData('tracking_url', $url);
    }
}
