<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Test\Unit\Model;

use Magento\Quote\Model\Quote\Item;

/**
 * Quote item stand-in: getProductId is a magic __call method on the item
 * model and cannot be configured on a PHPUnit mock — declared real here.
 */
class ItemStub extends Item
{
    /**
     * @return int
     */
    public function getProductId(): int
    {
        return parent::getProductId();
    }
}
