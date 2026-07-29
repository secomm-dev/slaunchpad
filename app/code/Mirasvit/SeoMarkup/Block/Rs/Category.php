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


declare(strict_types=1);

namespace Mirasvit\SeoMarkup\Block\Rs;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template\Context;
use Mirasvit\Seo\Api\Service\StateServiceInterface;
use Mirasvit\Seo\Api\Service\TemplateEngineServiceInterface;
use Mirasvit\SeoContent\Api\Data\TemplateInterface;
use Mirasvit\SeoMarkup\Model\Config\CategoryConfig;
use Mirasvit\SeoMarkup\Service\HtmlCleanerService;
use Mirasvit\SeoMarkup\Service\ProductRichSnippetsService;

class Category extends AbstractRsBlock
{
    /**
     * Hard cap on the number of products emitted into the category rich-snippet
     * markup in "Entire" product-offers mode, so very large categories don't
     * serialize the whole catalog into the OfferCatalog snippet.
     */
    const MAX_ENTIRE_PRODUCTS = 500;

    protected $category;

    protected $categoryConfig;

    private $templateEngineService;

    private $registry;

    private $stateService;

    public function __construct(
        CategoryConfig                 $categoryConfig,
        ProductCollectionFactory       $productCollectionFactory,
        TemplateEngineServiceInterface $templateEngineService,
        Registry                       $registry,
        Context                        $context,
        ProductRichSnippetsService     $productSnippetService,
        StateServiceInterface          $stateService,
        HtmlCleanerService             $htmlCleaner,
        Json                           $serializer
    ) {
        $this->categoryConfig        = $categoryConfig;
        $this->templateEngineService = $templateEngineService;
        $this->registry              = $registry;
        $this->stateService          = $stateService;

        parent::__construct($productCollectionFactory, $context, $productSnippetService, $htmlCleaner, $serializer);
    }

    public function getJsonData(): ?array
    {
        $this->category = $this->registry->registry('current_category');

        if (!$this->category) {
            return null;
        }

        if ($this->category->getId() == $this->_storeManager->getStore()->getRootCategoryId()) {
            return null;
        }

        if (!$this->isRsEnabled() || $this->stateService->isLandingPage()) {
            return null;
        }

        return $this->getResult();
    }

    protected function getEntityName(): string
    {
        return $this->category ? $this->category->getName() : '';
    }

    protected function getEntityImage(): string
    {
        if (!$this->category) {
            return '';
        }

        $imageUrl    = $this->category->getImageUrl();
        $imageConfig = $this->getImageConfig();

        if ($imageUrl && $imageConfig
            && ($imageConfig == CategoryConfig::IMAGE_YES || !$this->stateService->isNavigationPage())
        ) {
            return $this->_urlBuilder->escape(
                $this->_storeManager->getStore()->getBaseUrl() . ltrim($imageUrl, '/')
            );
        }

        return '';
    }

    protected function getEntityDescription(): string
    {
        if (!$this->category) {
            return '';
        }

        $value          = '';
        $objectManager  = ObjectManager::getInstance();
        /** @var \Mirasvit\SeoContent\Service\ContentService $contentService */
        $contentService = $objectManager->get('Mirasvit\SeoContent\Service\ContentService');
        $content        = $contentService->getCurrentContent(TemplateInterface::RULE_TYPE_CATEGORY);

        switch ($this->getDescriptionType()) {
            case CategoryConfig::DESCRIPTION_TYPE_DESCRIPTION:
                $description = $content->getData('category_description');
                $value       = !empty($description)
                    ? $description
                    : $this->templateEngineService->render('[category_description]', ['category' => $this->category]);
                break;
            case CategoryConfig::DESCRIPTION_TYPE_META:
                $description = $content->getData('meta_description');
                $value       = !empty($description)
                    ? $description
                    : $this->templateEngineService->render('[category_meta_description]', ['category' => $this->category]);
                break;
        }

        return $this->stripHtmlAndClean($value);
    }

    protected function getBaseProductCollection(): ProductCollection
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addCategoryFilter($this->category);
        $collection->addAttributeToFilter('visibility', Visibility::VISIBILITY_BOTH);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);

        return $collection;
    }

    protected function isRsEnabled(): bool
    {
        return $this->categoryConfig->isRsEnabled($this->getStoreId());
    }

    protected function getProductOffersType(): int
    {
        return $this->categoryConfig->getProductOffersType($this->getStoreId());
    }

    protected function getProductOffersFormat(): int
    {
        return $this->categoryConfig->getProductOffersFormat($this->getStoreId());
    }

    protected function getDescriptionType(): int
    {
        return $this->categoryConfig->getDescriptionType($this->getStoreId());
    }

    protected function getImageConfig(): int
    {
        return $this->categoryConfig->getImage($this->getStoreId());
    }

    protected function getAggregateBrandName(): string
    {
        return (string)$this->categoryConfig->getAggregateBrandName($this->getStoreId());
    }

    protected function isAggregateRatingEnabled(): bool
    {
        return $this->categoryConfig->isAggregateRatingEnabled($this->getStoreId());
    }

    protected function isExcludeZeroPriceProducts(): bool
    {
        return $this->categoryConfig->isExcludeZeroPriceProducts($this->getStoreId());
    }

    protected function getProductCollection(): ?ProductCollection
    {
        if ($this->productCollection === null) {
            $productOffersType = $this->getProductOffersType();

            switch ($productOffersType) {
                case CategoryConfig::PRODUCT_OFFERS_TYPE_CURRENT_PAGE:
                    /** @var \Magento\Catalog\Block\Product\ListProduct $categoryProductsListBlock */
                    $categoryProductsListBlock = $this->getLayout()->getBlock('category.products.list');

                    if ($categoryProductsListBlock) {
                        $loadedCollection = $categoryProductsListBlock->getLoadedProductCollection();

                        $ids = [];
                        foreach ($loadedCollection as $product) {
                            $ids[] = (int)$product->getId();
                        }

                        $collection = $this->productCollectionFactory->create();
                        if ($this->isSimpleOfferFormat()) {
                            $collection->addAttributeToSelect(['name', 'url_key', 'image']);
                        } else {
                            $collection->addAttributeToSelect('*');
                            $collection->addFinalPrice();
                        }
                        $collection->addAttributeToFilter('entity_id', ['in' => $ids ?: [0]]);

                        // Preserve the listing order so ItemList positions line up with the grid.
                        if ($ids) {
                            $collection->getSelect()->order(
                                new \Zend_Db_Expr('FIELD(e.entity_id, ' . implode(',', $ids) . ')')
                            );
                        }
                        $collection->load();

                        $this->productCollection = $collection;
                    }
                    break;

                case CategoryConfig::PRODUCT_OFFERS_TYPE_ENTIRE:
                    $collection = $this->productCollectionFactory->create();
                    $collection->addAttributeToSelect('*');
                    $collection->addCategoryFilter($this->category);
                    $collection->addAttributeToFilter('visibility', Visibility::VISIBILITY_BOTH);
                    $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
                    $collection->addFinalPrice();

                    // Behaves as before: list the whole category, but cap the
                    // product count so a huge category can't bloat the snippet
                    // (and the page) with the entire catalog.
                    $collection->setPageSize(self::MAX_ENTIRE_PRODUCTS);

                    $collection->load();

                    $this->productCollection = $collection;
                    break;
            }
        }

        return $this->productCollection;
    }
}
