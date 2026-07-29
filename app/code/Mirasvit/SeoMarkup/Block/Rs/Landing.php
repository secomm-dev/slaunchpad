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

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogSearch\Model\Advanced\Request\BuilderFactory as RequestBuilderFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template\Context;
use Magento\Search\Model\SearchEngine;
use Mirasvit\Seo\Api\Service\StateServiceInterface;
use Mirasvit\Seo\Api\Service\TemplateEngineServiceInterface;
use Mirasvit\SeoContent\Api\Data\TemplateInterface;
use Mirasvit\SeoMarkup\Model\Config\LandingConfig;
use Mirasvit\SeoMarkup\Service\HtmlCleanerService;
use Mirasvit\SeoMarkup\Service\ProductRichSnippetsService;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Landing extends AbstractRsBlock
{
    private $page;

    protected $landingConfig;

    private $templateEngineService;

    private $stateService;

    private $catalogLayer;

    private $request;

    private $requestBuilderFactory;

    private $searchEngine;

    private $objectManager;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        LandingConfig                  $landingConfig,
        ProductCollectionFactory       $productCollectionFactory,
        TemplateEngineServiceInterface $templateEngineService,
        Context                        $context,
        ProductRichSnippetsService     $productSnippetService,
        StateServiceInterface          $stateService,
        LayerResolver                  $layerResolver,
        RequestInterface               $request,
        RequestBuilderFactory          $requestBuilderFactory,
        SearchEngine                   $searchEngine,
        ObjectManagerInterface         $objectManager,
        HtmlCleanerService             $htmlCleaner,
        Json                           $serializer
    ) {
        $this->landingConfig         = $landingConfig;
        $this->templateEngineService = $templateEngineService;
        $this->stateService          = $stateService;
        $this->catalogLayer          = $layerResolver->get();
        $this->request               = $request;
        $this->requestBuilderFactory = $requestBuilderFactory;
        $this->searchEngine          = $searchEngine;
        $this->objectManager         = $objectManager;

        parent::__construct($productCollectionFactory, $context, $productSnippetService, $htmlCleaner, $serializer);
    }

    public function getJsonData(): ?array
    {
        if (!$this->isRsEnabled()) {
            return null;
        }

        $this->page = $this->stateService->getLandingPage();

        if (!$this->page) {
            return null;
        }

        return $this->getResult();
    }

    protected function getEntityName(): string
    {
        return $this->page ? $this->page->getName() : '';
    }

    protected function getEntityImage(): string
    {
        if (!$this->page || !$this->page->getImage()) {
            return '';
        }

        $imageConfig = $this->getImageConfig();

        if ($imageConfig && ($imageConfig == LandingConfig::IMAGE_YES || !$this->stateService->isNavigationPage())) {
            $pageImageUrlService = $this->objectManager->create('Mirasvit\LandingPage\Service\ImageUrlService');
            $imageUrl            = $pageImageUrlService->getImageUrl($this->page->getImage());

            return $this->_urlBuilder->escape(
                $this->_storeManager->getStore()->getBaseUrl() . ltrim($imageUrl, '/')
            );
        }

        return '';
    }

    protected function getEntityDescription(): string
    {
        if (!$this->page) {
            return '';
        }

        $value          = '';
        $objectManager  = ObjectManager::getInstance();
        /** @var \Mirasvit\SeoContent\Service\ContentService $contentService */
        $contentService = $objectManager->get('Mirasvit\SeoContent\Service\ContentService');
        $content        = $contentService->getCurrentContent(TemplateInterface::RULE_TYPE_CATEGORY);

        switch ($this->getDescriptionType()) {
            case LandingConfig::DESCRIPTION_TYPE_DESCRIPTION:
                $description = $content->getData('category_description');
                $value       = !empty($description)
                    ? $description
                    : $this->templateEngineService->render('[category_description]', ['category' => $this->page]);
                break;
            case LandingConfig::DESCRIPTION_TYPE_META:
                $description = $content->getData('meta_description');
                $value       = !empty($description)
                    ? $description
                    : $this->templateEngineService->render('[category_meta_description]', ['category' => $this->page]);
                break;
        }

        return $this->stripHtmlAndClean($value);
    }

    protected function getBaseProductCollection(): ProductCollection
    {
        $collection = $this->productCollectionFactory->create();

        $categories = $this->page->getCategories();
        if ($categories) {
            $collection->addCategoriesFilter(['in' => explode(',', $categories)]);
        }

        $filterRepository = $this->objectManager->create('Mirasvit\LandingPage\Repository\FilterRepository');
        $filterCollection = $filterRepository->getByPageId((int)$this->page->getId());
        foreach ($filterCollection as $filter) {
            $optionIds        = explode(',', $filter->getOptionIds());
            $filterConditions = [];
            foreach ($optionIds as $optionId) {
                $filterConditions[] = ['finset' => $optionId];
            }
            $collection->addFieldToFilter($filter->getAttributeCode(), $filterConditions);
        }

        $collection->addAttributeToFilter('visibility', Visibility::VISIBILITY_BOTH);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->addStoreFilter($this->getStoreId());

        return $collection;
    }

    protected function isRsEnabled(): bool
    {
        return $this->landingConfig->isRsEnabled($this->getStoreId());
    }

    protected function getProductOffersType(): int
    {
        return $this->landingConfig->getProductOffersType($this->getStoreId());
    }

    protected function getProductOffersFormat(): int
    {
        return $this->landingConfig->getProductOffersFormat($this->getStoreId());
    }

    protected function getDescriptionType(): int
    {
        return $this->landingConfig->getDescriptionType($this->getStoreId());
    }

    protected function getImageConfig(): int
    {
        return $this->landingConfig->getImage($this->getStoreId());
    }

    protected function getAggregateBrandName(): string
    {
        return (string)$this->landingConfig->getAggregateBrandName($this->getStoreId());
    }

    protected function isAggregateRatingEnabled(): bool
    {
        return $this->landingConfig->isAggregateRatingEnabled($this->getStoreId());
    }

    protected function isExcludeZeroPriceProducts(): bool
    {
        return $this->landingConfig->isExcludeZeroPriceProducts($this->getStoreId());
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function getProductCollection(): ?ProductCollection
    {
        if ($this->productCollection === null) {
            $productOffersType = $this->getProductOffersType();

            switch ($productOffersType) {
                case LandingConfig::PRODUCT_OFFERS_TYPE_CURRENT_PAGE:
                    /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $loadedCollection */
                    $loadedCollection = $this->catalogLayer->getProductCollection();

                    $collection = clone $loadedCollection;
                    $ids        = [];

                    foreach ($collection as $product) {
                        $ids[] = $product->getId();
                    }

                    $collection->addAttributeToSelect('*');
                    $collection->addAttributeToFilter('entity_id', ['in' => $ids]);
                    $collection->addFinalPrice();
                    $collection->load();

                    $this->productCollection = $collection;
                    break;

                case LandingConfig::PRODUCT_OFFERS_TYPE_ENTIRE:
                    $collection = $this->productCollectionFactory->create();

                    $categories = $this->page->getCategories();
                    if ($categories) {
                        $collection->addCategoriesFilter(['in' => explode(',', $categories)]);
                    }

                    $filterRepository = $this->objectManager->create('Mirasvit\LandingPage\Repository\FilterRepository');
                    $filterCollection = $filterRepository->getByPageId((int)$this->page->getId());
                    foreach ($filterCollection as $filter) {
                        $optionIds        = explode(',', $filter->getOptionIds());
                        $filterConditions = [];
                        foreach ($optionIds as $optionId) {
                            $filterConditions[] = ['finset' => $optionId];
                        }
                        $collection->addFieldToFilter($filter->getAttributeCode(), $filterConditions);
                    }

                    $attributeValue = $this->request->getParam('landing_search');
                    if (!empty($attributeValue)) {
                        $requestBuilder = $this->requestBuilderFactory->create()
                            ->bind('search_term', $attributeValue)
                            ->bindDimension('scope', $this->getStoreId())
                            ->setRequestName('catalogsearch_fulltext');

                        $result = $this->searchEngine->search($requestBuilder->create());
                        $ids    = [];

                        foreach ($result->getIterator() as $item) {
                            $ids[] = $item->getId();
                        }

                        $collection->addIdFilter($ids);
                    }

                    $collection->addAttributeToFilter('visibility', Visibility::VISIBILITY_BOTH);
                    $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
                    $collection->addStoreFilter($this->getStoreId());
                    $collection->addFinalPrice();
                    $collection->load();

                    $this->productCollection = $collection;
                    break;
            }
        }

        return $this->productCollection;
    }
}
