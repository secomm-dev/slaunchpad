<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Api\Data;

use Magento\Quote\Model\Quote\Item\AbstractItem;

interface PackageItemInterface
{
    /**
     * @return string
     */
    public function getServiceId(): string;

    /**
     * @param string $serviceId
     * @return mixed
     */
    public function setServiceId(string $serviceId):static;

    /**
     * @param float $length
     * @return $this
     */
    public function setServiceLength(float $length):static;

    /**
     * @return float
     */
    public function getServiceLength():float;

    /**
     * @param float $width
     * @return $this
     */
    public function setServiceWidth(float $width):static;

    /**
     * @return float
     */
    public function getServiceWidth():float;

    /**
     * @param float $height
     * @return $this
     */
    public function setServiceHeight(float $height):static;

    /**
     * @return float
     */
    public function getServiceHeight():float;

    /**
     * @param float $weight
     * @return $this
     */
    public function setServiceWeight(float $weight):static;

    /**
     * @return float
     */
    public function getServiceWeight():float;

    /**
     * @return array
     */
    public function getItems(): array;

    /**
     * @param array $quote
     * @return mixed
     */
    public function setItems(array $quote): static;

    /**
     * @param AbstractItem $item
     * @return mixed
     */
    public function addItem(AbstractItem $item): static;

    /**
     * @return string
     */
    public function getCityId():string;

    /**
     * @param $cityId
     * @return $this
     */
    public function setCityId($cityId):static;

    /**
     * @return int
     */
    public function getServiceSize(): int;

    /**
     * @return int
     */
    public function count():int;
}
