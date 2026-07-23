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
 * @package     Mageplaza_ThankYouPage
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ThankYouPage\Block\Product;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Widget\Block\BlockInterface;
use Mageplaza\ThankYouPage\Helper\Data;
use Mageplaza\ThankYouPage\Model\Config\Source\Pagetype;
use Mageplaza\ThankYouPage\Model\Template;

/**
 * Class CrossSell
 * @package Mageplaza\ThankYouPage\Block\Product
 */
class CrossSell extends AbstractProduct implements BlockInterface
{
    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var Visibility
     */
    protected $_productVisibility;

    /**
     * CrossSell constructor.
     *
     * @param Context $context
     * @param CollectionFactory $productCollectionFactory
     * @param Data $helperData
     * @param Session $checkoutSession
     * @param Visibility $productVisibility
     * @param array $data
     */
    public function __construct(
        Context $context,
        CollectionFactory $productCollectionFactory,
        Data $helperData,
        Session $checkoutSession,
        Visibility $productVisibility,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->productCollectionFactory = $productCollectionFactory;
        $this->helperData               = $helperData;
        $this->checkoutSession          = $checkoutSession;
        $this->_productVisibility       = $productVisibility;
    }

    /**
     * @return Template|null
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getTemplateData()
    {
        return $this->helperData->getTemplateData(Pagetype::ORDER);
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getTitleProductSlider()
    {
        if ($this->getTemplateData() == null || !$this->getTemplateData()->getEnableProductSlider()) {
            return '';
        }

        return $this->getTemplateData()->getProductSliderTitle();
    }

    /**
     * @return array|Collection
     * @throws NoSuchEntityException
     */
    public function getProductCollection()
    {
        if ($this->getTemplateData() == null || !$this->getTemplateData()->getEnableProductSlider()) {
            return [];
        }

        $productIds = array_unique($this->getOrderProductIds());
        if (empty($productIds)) {
            return [];
        }

        $collection = $this->productCollectionFactory->create()
            ->addIdFilter($productIds)
            ->addStoreFilter()
            ->setVisibility($this->_productVisibility->getVisibleInCatalogIds());
        $this->_addProductAttributesAndPrices($collection);

        $limit      = $this->getTemplateData()->getProductSliderLimit();
        $collection = ($limit != 0) ? $collection->setPageSize($limit) : [];

        return $collection;
    }

    /**
     * @return array|mixed|null
     */
    protected function getOrderProductIds()
    {
        $productIds = $this->getData('order_product_ids');
        if ($productIds === null) {
            $productIds = [];
            $order      = $this->checkoutSession->getLastRealOrder();
            foreach ($order->getAllItems() as $item) {
                $product = $item->getProduct();
                if ($product) {
                    $productIds += $product->getCrossSellProductIds();
                }
            }
            $this->setData('order_product_ids', $productIds);
        }

        return $productIds;
    }

    /**
     * @param $type
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function canShow($type)
    {
        return in_array($type, $this->getDisplayAdditional());
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getDisplayAdditional()
    {
        if ($this->getTemplateData() == null || !$this->getTemplateData()->getEnableProductSlider()) {
            return [];
        }
        $config  = $this->getTemplateData()->getData();
        $display = $config['product_slider_additional'];
        if (!is_array($display)) {
            $display = explode(',', $display);
        }

        return $display;
    }

    /**
     * @return mixed
     */
    public function isOrderSuccessPageEnable()
    {
        return $this->helperData->isOrderSuccessPageEnable();
    }
}
