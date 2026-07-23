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

namespace Mageplaza\ExtraFee\Helper;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Class ValidateHelper
 * @package Mageplaza\ExtraFee\Helper
 */
class ValidateHelper
{
    /**
     * @var ProductCollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * ValidateHelper constructor.
     *
     * @param ProductCollectionFactory $productCollectionFactory
     * @param Data $helperData
     */
    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        Data $helperData
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->helperData               = $helperData;
    }

    /**
     * Assign products to quote items collection
     * Cloned from Magento\Quote\Model\ResourceModel\Quote\Item\Collection::_assignProducts
     *
     * @param AbstractCollection $collection
     *
     * @return Product
     */
    public function assignProducts($collection, $rootProduct)
    {
        $productIds = [];
        foreach ($collection as $item) {
            $productIds[] = $item->getProductId();
        }

        if (!empty($productIds)) {
            // Get active attributes from Data helper (shared method)
            $productCollection = $this->productCollectionFactory->create()
                ->setStoreId($collection->getStoreId())
                ->addIdFilter($productIds);
            $activeAttributes  = $this->helperData->getActiveAttributes();
            $productCollection->addAttributeToSelect($activeAttributes);

            foreach ($collection as $item) {
                $product = $productCollection->getItemById($item->getProductId());
                if ($rootProduct->getId() == $product->getId()) {
                    $rootProduct = $product;
                }
                if ($product) {
                    $item->setProduct($product);
                }
            }
        }

        return $rootProduct;
    }
}
