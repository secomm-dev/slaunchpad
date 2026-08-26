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
    // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod -- the
    // pass-through override is the point of this stub: turning the magic
    // __call getter into a real method so PHPUnit can configure it.

    /**
     * @return int
     */
    public function getProductId(): int
    {
        return parent::getProductId();
    }
}
