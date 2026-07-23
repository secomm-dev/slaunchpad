<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ExtraFee\Model\Rule\Condition;

use Magento\Framework\Model\AbstractModel;
use Magento\SalesRule\Model\Rule\Condition\Product\Found;

/**
 * Class Product
 * @package Mageplaza\ExtraFee\Model\Rule\Condition
 */
class Product extends Found
{
    /**
     * @param AbstractModel $model
     *
     * @return bool
     */
    public function validate(AbstractModel $model)
    {
        return parent::validate($model);
    }
}
