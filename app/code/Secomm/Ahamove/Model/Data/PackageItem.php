<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model\Data;

use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Secomm\Ahamove\Api\Data\PackageItemInterface;

class PackageItem extends DataObject implements PackageItemInterface
{
    const SERVICE_ID = 'service_id';
    const QUOTE_ITEM = 'items';
    const CITY_ID = 'city_id';
    const SERVICE_WIDTH = 'service_width';
    const SERVICE_HEIGHT = 'service_height';
    const SERVICE_WEIGHT = 'service_weight';
    const SERVICE_LENGTH = 'service_length';

    /**
     * @param string $serviceId
     * @return PackageInterface
     */
    public function setServiceId(string $serviceId): static
    {
        $this->setData(self::SERVICE_ID, $serviceId);
        return $this;
    }

    /**
     * @return string
     */
    public function getServiceId(): string
    {
        return $this->getData(self::SERVICE_ID);
    }

    /**
     * @return array
     */
    public function getItems(): array
    {
        return $this->getData('items');
    }

    /**
     * @param AbstractItem[] $items
     * @return PackageInterface
     */
    public function setItems(array $items): static
    {
        $this->setData(self::QUOTE_ITEM, $items);
        return $this;
    }

    /**
     * @param AbstractItem $item
     * @return PackageInterface
     */
    public function addItem(AbstractItem $item): static
    {
        $items = $this->getData(self::QUOTE_ITEM) ?? [];
        $items[] = $item;
        $this->setData(self::QUOTE_ITEM, $items);
        return $this;
    }

    /**
     * @param $cityId
     * @return static
     */
    public function setCityId($cityId): static
    {
        $this->setData(self::CITY_ID, $cityId);
        return $this;
    }

    /**
     * @return string
     */
    public function getCityId():string
    {
        return $this->getData(self::CITY_ID);
    }

    /**
     * @param float $width
     * @return $this
     */
    public function setServiceWidth(float $width):static
    {
        $this->setData(self::SERVICE_WIDTH, $width);
        return $this;
    }

    /**
     * @return float
     */
    public function getServiceWidth():float
    {
        return $this->getData(self::SERVICE_WIDTH);
    }

    /**
     * @param float $height
     * @return $this
     */
    public function setServiceHeight(float $height):static
    {
        $this->setData(self::SERVICE_HEIGHT, $height);
        return $this;
    }

    /**
     * @return float
     */
    public function getServiceHeight():float
    {
        return $this->getData(self::SERVICE_HEIGHT);
    }

    /**
     * @param float $weight
     * @return $this
     */
    public function setServiceWeight(float $weight):static
    {
        $this->setData(self::SERVICE_WEIGHT, $weight);
        return $this;
    }

    /**
     * @return float
     */
    public function getServiceWeight():float
    {
        return $this->getData(self::SERVICE_WEIGHT);
    }

    /**
     * @return float
     */
    public function getServiceLength():float
    {
        return $this->getData(self::SERVICE_LENGTH);
    }

    /**
     * @param float $length
     * @return $this
     */
    public function setServiceLength(float $length):static
    {
        $this->setData(self::SERVICE_LENGTH, $length);
        return $this;
    }

    /**
     * @return int
     */
    public function getServiceSize(): int
    {
        return $this->getData(self::SERVICE_LENGTH) * $this->getData(self::SERVICE_WIDTH) * $this->getData(self::SERVICE_HEIGHT);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        if ($this->hasData(self::QUOTE_ITEM)) {
            return count($this->getData(self::QUOTE_ITEM));
        } else {
            return 0;
        }
    }
}
