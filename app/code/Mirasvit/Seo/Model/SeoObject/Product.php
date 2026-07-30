<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\Seo\Model\SeoObject;

class Product extends \Mirasvit\Seo\Model\SeoObject\AbstractObject
{
    /**
     * @var \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var \Mirasvit\Seo\Helper\Data
     */
    protected $seoData;

    /**
     * @var \Mirasvit\Seo\Model\SeoObject\StoreFactory
     */
    protected $objectStoreFactory;

    /**
     * @var \Magento\Framework\Stdlib\StringUtils
     */
    protected $string;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @var \Magento\Catalog\Model\CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var \Mirasvit\Seo\Model\Config
     */
    protected $config;

    /**
     * @param \Magento\Framework\Registry                                     $registry
     * @param \Mirasvit\Seo\Helper\Data                                       $seoData
     * @param \Mirasvit\Seo\Model\SeoObject\StoreFactory                      $objectStoreFactory
     * @param \Magento\Framework\Stdlib\StringUtils                           $string
     * @param \Magento\Store\Model\StoreManagerInterface                      $storeManager
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
     * @param \Magento\Catalog\Model\CategoryFactory                          $categoryFactory
     * @param \Mirasvit\Seo\Model\Config                                      $config
     */
    public function __construct(
        \Magento\Framework\Registry                                     $registry,
        \Mirasvit\Seo\Helper\Data                                       $seoData,
        \Mirasvit\Seo\Model\SeoObject\StoreFactory                      $objectStoreFactory,
        \Magento\Framework\Stdlib\StringUtils                           $string,
        \Magento\Store\Model\StoreManagerInterface                      $storeManager,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Model\CategoryFactory                          $categoryFactory,
        \Mirasvit\Seo\Model\Config                                      $config
    ) {
        $this->registry = $registry;
        $this->seoData = $seoData;
        $this->objectStoreFactory = $objectStoreFactory;
        $this->string = $string;
        $this->storeManager = $storeManager;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryFactory = $categoryFactory;
        $this->config = $config;
        $this->_construct();
    }

    /**
     * @var \Magento\Catalog\Model\Product|null
     */
    protected $product;
    /**
     * @var \Magento\Catalog\Model\Category
     */
    protected $category;
    /**
     * @var array
     */
    protected $parseObjects = [];

    public function _construct()
    {
        parent::_construct();
        $product = $this->registry->registry('current_product');
        if (!$product instanceof \Magento\Catalog\Model\Product) {
            $product = $this->registry->registry('product');
        }
        if (!$product instanceof \Magento\Catalog\Model\Product) {
            return;
        }
        $this->product = $product;

        $this->parseObjects['product'] = $product;

        $this->setAdditionalVariable('product', 'url', $product->getProductUrl());
        $this->setAdditionalVariable('product', 'final_price', $product->getFinalPrice());
        $this->setAdditionalVariable(
            'product',
            'final_price_minimal',
            $this->seoData->getCurrentProductFinalPrice($product)
        );
        $this->setAdditionalVariable(
            'product',
            'final_price_range',
            $this->seoData->getCurrentProductFinalPriceRange($product)
        );

        $categoryId = $product->getSeoCategory();
        $this->category = $this->registry->registry('current_category');

        if ($this->category && !$categoryId) {
            $this->parseObjects['category'] = $this->category;
        } else {
            if (!$categoryId) {
                $categoryIds = $product->getCategoryIds();
                if (count($categoryIds) > 0) {
                    //we need this for multi websites configuration
                    $categoryRootId = $this->storeManager->getStore()->getRootCategoryId();
                    $category = $this->categoryCollectionFactory->create()
                                ->addFieldToFilter('path', ['like' => "%/{$categoryRootId}/%"])
                                ->addFieldToFilter('entity_id', $categoryIds)
                                ->setOrder('level', 'desc')
                                ->setOrder('entity_id', 'desc')
                                ->getFirstItem()
                            ;
                    $categoryId = $category->getId();
                }
            }
            //load category with flat data attributes
            $category = $this->categoryFactory->create()->load($categoryId);
            $this->category = $category;
            $this->parseObjects['category'] = $category;
            if (!$this->registry->registry('seo_current_category')) {
                // to be sure that register will not be done twice
                $this->registry->register('seo_current_category', $category);
            };
        }

        $this->parseObjects['store'] = $this->objectStoreFactory->create();

        $this->init();
    }

    /**
     * @return void
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function init()
    {
        if ($this->product === null) {
            return;
        }
        $product = $this->product;
        $isProductMetaTagsUsed = $this->config->isProductMetaTagsUsed();
        $templateData = $this->registry->registry('m_seo_template_data');
        $productMetaTitle = trim($product->getMetaTitle());
        //magento use getMetaKeyword instead getMetaKeywords
        $productMetaKeyword = trim($product->getMetaKeyword());
        $productMetaDescription = trim($product->getMetaDescription());

        if ($isProductMetaTagsUsed && !$this->getMetaTitle() && $productMetaTitle) {
            $this->setMetaTitle($this->parse($productMetaTitle));
        }

        if ($isProductMetaTagsUsed && !$this->getMetaKeyword() && $productMetaKeyword) {
            $this->setMetaKeywords($this->parse($productMetaKeyword));
        }

        if ($isProductMetaTagsUsed && !$this->getMetaDescription() && $productMetaDescription) {
            $this->setMetaDescription($this->parse($productMetaDescription));
        }

        if ((!$isProductMetaTagsUsed || !$productMetaTitle)
            && isset($templateData['meta_title']) && trim($templateData['meta_title'])) {
            $this->setMetaTitle($templateData['meta_title']);
        }
        if ((!$isProductMetaTagsUsed || !$productMetaKeyword)
            && isset($templateData['meta_keywords']) && trim($templateData['meta_keywords'])) {
            $this->setMetaKeywords($templateData['meta_keywords']);
        }
        if ((!$isProductMetaTagsUsed || !$productMetaDescription)
            && isset($templateData['meta_description']) && trim($templateData['meta_description'])) {
            $this->setMetaDescription($templateData['meta_description']);
        }

        if (!$this->getTitle()) {
            $this->setTitle($product->getName());
        }

        if (!$this->getMetaTitle()) {
            if ($productMetaTitle) {
                $this->setMetaTitle($this->parse($productMetaTitle));
            } else {
                $this->setMetaTitle($product->getName());
            }
        }

        if (!$this->getMetaKeywords()) {
            if ($productMetaKeyword) {
                $this->setMetaKeywords($this->parse($productMetaKeyword));
            } else {
                $this->setMetaKeywords($product->getName());
            }
        }

        if (!$this->getMetaDescription()) {
            if ($productMetaDescription) {
                $this->setMetaDescription($this->parse($productMetaDescription));
            } elseif ($product->getDescription()) {
                $this->setMetaDescription($this->string->substr($product->getDescription(), 0, 255));
            } else {
                $this->setMetaDescription($product->getName());
            }
        }
    }
}
