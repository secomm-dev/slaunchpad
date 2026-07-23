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

namespace Mageplaza\Lookbook\Plugin\Controller\Catalog\Product;

use Mageplaza\Lookbook\Helper\Data;

/**
 * Class View
 * @package Mageplaza\Lookbook\Plugin\Controller\Catalog\Product
 */
class View
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * View constructor.
     *
     * @param Data $helperData
     */
    public function __construct(
        Data $helperData
    ) {
        $this->helperData = $helperData;
    }

    /**
     * @param \Magento\Catalog\Controller\Product\View $subject
     * @param $result
     *
     * @return mixed
     */
    public function afterExecute(\Magento\Catalog\Controller\Product\View $subject, $result)
    {
        if (!$this->helperData->isEnabled() || !$subject->getRequest()->getParam('mplookbook')) {
            return $result;
        }

        $result->getLayout()->unsetElement('product.info.review');
        $result->getLayout()->unsetElement('reviews.tab');
        $result->getLayout()->unsetElement('product.info.details');
        $result->getLayout()->unsetElement('product.info.upsell');
        $result->getLayout()->unsetElement('catalog.product.related');
        $result->getLayout()->unsetElement('product.info.mailto');
        $result->getLayout()->unsetElement('header.container');
        $result->getLayout()->unsetElement('product_viewed_counter');
        $result->getLayout()->unsetElement('footer-container');
        $result->getLayout()->unsetElement('page.top');
        $result->getLayout()->unsetElement('copyright');
        $result->getLayout()->unsetElement('page.bottom');
        $result->getLayout()->unsetElement('review_list');
        $result->getLayout()->unsetElement('upsell');

        return $result;
    }
}
