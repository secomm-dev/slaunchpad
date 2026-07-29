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
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template\Context;
use Mirasvit\SeoMarkup\Model\Config\CategoryConfig;
use Mirasvit\SeoMarkup\Service\HtmlCleanerService;
use Mirasvit\SeoMarkup\Service\ProductRichSnippetsService;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Brand extends AbstractRsBlock
{
    protected $categoryConfig;

    private $moduleManager;

    private $objectManager;

    public function __construct(
        ModuleManager              $moduleManager,
        ObjectManagerInterface     $objectManager,
        CategoryConfig             $categoryConfig,
        ProductCollectionFactory   $productCollectionFactory,
        Context                    $context,
        ProductRichSnippetsService $productSnippetService,
        HtmlCleanerService         $htmlCleaner,
        Json                       $serializer
    ) {
        $this->moduleManager  = $moduleManager;
        $this->objectManager  = $objectManager;
        $this->categoryConfig = $categoryConfig;

        parent::__construct($productCollectionFactory, $context, $productSnippetService, $htmlCleaner, $serializer);
    }

    public function getJsonData(): ?array
    {
        if (!$this->moduleManager->isEnabled('Mirasvit_Brand')) {
            return null;
        }

        if (!$this->isRsEnabled()) {
            return null;
        }

        $brandTitle = $this->getBrandTitle();

        if (empty($brandTitle)) {
            return null;
        }

        return $this->getResult();
    }

    protected function getEntityName(): string
    {
        return $this->getBrandTitle();
    }

    protected function getEntityImage(): string
    {
        return '';
    }

    protected function getEntityDescription(): string
    {
        return '';
    }

    protected function getAggregateBrandName(): string
    {
        $brandName = (string)$this->categoryConfig->getAggregateBrandName($this->getStoreId());

        if (empty($brandName)) {
            $brandName = $this->getBrandTitle();
        }

        return $brandName;
    }

    protected function getBaseProductCollection(): ProductCollection
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToFilter(
            $this->getBrandAttributeCode(),
            $this->getBrandAttributeValue()
        );
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

            if ($productOffersType === CategoryConfig::PRODUCT_OFFERS_TYPE_ENTIRE) {
                $pageSize = $this->categoryConfig->getDefaultPageSize($this->getStoreId());
                $pageNum  = 1;

                /** @var \Magento\Framework\View\Element\AbstractBlock $toolbar */
                $toolbar = $this->getLayout()->getBlock('product_list_toolbar');
                if ($toolbar) {
                    $pageSize = $toolbar->getLimit();
                }

                /** @var \Magento\Framework\View\Element\AbstractBlock $pager */
                $pager = $this->getLayout()->getBlock('product_list_toolbar_pager');
                if ($pager) {
                    $pageNum = $pager->getCurrentPage();
                }

                $collection = $this->productCollectionFactory->create();
                $collection->addAttributeToSelect('*');
                $collection->addAttributeToFilter(
                    $this->getBrandAttributeCode(),
                    $this->getBrandAttributeValue()
                );
                $collection->addAttributeToFilter('visibility', Visibility::VISIBILITY_BOTH);
                $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
                $collection->addFinalPrice();
                $collection->setPageSize($pageSize)->setCurPage($pageNum);
                $collection->load();

                $this->productCollection = $collection;
            } else {
                /** @var \Magento\Catalog\Block\Product\ListProduct $categoryProductsListBlock */
                $categoryProductsListBlock = $this->getLayout()->getBlock('category.products.list');

                if ($categoryProductsListBlock) {
                    /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $loadedCollection */
                    $loadedCollection = $categoryProductsListBlock->getLoadedProductCollection();

                    $collection = clone $loadedCollection;
                    if ($this->isSimpleOfferFormat()) {
                        $collection->addAttributeToSelect('image');
                    } else {
                        $ids = [];
                        foreach ($collection as $product) {
                            $ids[] = $product->getId();
                        }

                        $collection->addAttributeToSelect('*');
                        $collection->addAttributeToFilter('entity_id', ['in' => $ids]);
                        $collection->addFinalPrice();
                    }
                    $collection->load();

                    $this->productCollection = $collection;
                }
            }
        }

        return $this->productCollection;
    }

    private function getBrandTitle(): string
    {
        return (string)$this->getBrandRegistry()->getBrandPage()->getBrandTitle();
    }

    private function getBrandAttributeCode(): string
    {
        return (string)$this->getBrandRegistry()->getBrand()->getAttributeCode();
    }

    private function getBrandAttributeValue(): string
    {
        return (string)$this->getBrandRegistry()->getBrand()->getValue();
    }

    private function getBrandRegistry()
    {
        return $this->objectManager->get('Mirasvit\Brand\Registry');
    }
}
