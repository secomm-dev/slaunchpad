<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Model;

use Magento\Framework\DataObject;
use Secomm\Ahamove\Api\Data\PackageItemInterface;
use Secomm\Ahamove\Api\PackageInterface;
use Secomm\Ahamove\Model\Data\PackageItem;

class Package extends DataObject implements PackageInterface
{
    const ITEMS = 'services';

    /**
     * @return array
     */
    public function getItems(): array
    {
        return $this->getData(self::ITEMS);
    }

    /**
     * @param array $items
     * @return PackageInterface
     */
    public function setItems(array $items): static
    {
        $this->setData(self::ITEMS, $items);
        return $this;
    }

    /**
     * @param PackageItemInterface|PackageItem $item
     * @return Package
     */
    public function addItem(PackageItemInterface|Data\PackageItem $item): static
    {
        $items = $this->getData(self::ITEMS) ?? [];
        $items[] = $item;
        $this->setData(self::ITEMS, $items);
        return $this;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        if ($this->hasData(self::ITEMS)) {
            return count($this->getData(self::ITEMS));
        } else {
            return 0;
        }
    }
}
