<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Api;

use Secomm\Ahamove\Model\Data\PackageItem;

interface PackageInterface
{
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
     * @param PackageItem $item
     * @return mixed
     */
    public function addItem(PackageItem $item): static;

    /**
     * @return int
     */
    public function count():int;
}
