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
 * @package     Mageplaza_Lookbook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Lookbook\Ui\DataProvider\Product;

use Magento\Catalog\Model\Product\Visibility;

/**
 * Class ProductDataProvider
 * @package Mageplaza\Lookbook\Ui\DataProvider\Product
 */
class ProductDataProvider extends \Magento\Catalog\Ui\DataProvider\Product\ProductDataProvider
{
    /**
     * @inheritDoc
     */
    public function getCollection()
    {
        $collection = parent::getCollection();
        $collection->addFieldToFilter('status', ['eq' => 1]);
        $collection->addAttributeToFilter('visibility', [
            'in' => [
                Visibility::VISIBILITY_IN_CATALOG,
                Visibility::VISIBILITY_BOTH
            ]
        ]);

        return $collection;
    }
}
