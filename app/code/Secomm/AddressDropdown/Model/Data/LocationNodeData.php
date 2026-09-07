<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Data;

use Magento\Framework\DataObject;
use Secomm\AddressDropdown\Api\Data\LocationNodeInterface;

class LocationNodeData extends DataObject implements LocationNodeInterface
{
    /**
     * @inheritDoc
     */
    public function getCityId(): int
    {
        return (int)$this->getData(self::CITY_ID);
    }

    /**
     * @inheritDoc
     */
    public function getDefaultName(): string
    {
        return (string)$this->getData(self::DEFAULT_NAME);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return (string)($this->getData(self::NAME) ?? $this->getData(self::DEFAULT_NAME));
    }

    /**
     * @inheritDoc
     */
    public function getDepth(): int
    {
        return (int)$this->getData(self::DEPTH);
    }

    /**
     * @inheritDoc
     */
    public function getParentCityId(): ?int
    {
        $parent = $this->getData(self::PARENT_CITY_ID);

        return $parent === null ? null : (int)$parent;
    }

    /**
     * @inheritDoc
     */
    public function getRegionId(): int
    {
        return (int)$this->getData(self::REGION_ID);
    }

    /**
     * @inheritDoc
     */
    public function hasChildren(): bool
    {
        return (bool)$this->getData(self::HAS_CHILDREN);
    }
}
