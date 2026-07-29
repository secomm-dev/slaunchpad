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

use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\Store;
use Mirasvit\SeoMarkup\Model\Config;
use Mirasvit\SeoMarkup\Model\Config\AbstractSnippetConfig;
use Mirasvit\SeoMarkup\Service\HtmlCleanerService;
use Mirasvit\SeoMarkup\Service\ProductRichSnippetsService;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractRsBlock extends Template
{
    protected $productCollectionFactory;

    protected $productSnippetService;

    protected $productCollection;

    protected $htmlCleaner;

    protected $serializer;

    private $storeId;

    public function __construct(
        ProductCollectionFactory   $productCollectionFactory,
        Context                    $context,
        ProductRichSnippetsService $productSnippetService,
        HtmlCleanerService         $htmlCleaner,
        Json                       $serializer
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productSnippetService    = $productSnippetService;
        $this->htmlCleaner              = $htmlCleaner;
        $this->serializer               = $serializer;

        parent::__construct($context);
    }

    protected function _toHtml(): string
    {
        $data = $this->getJsonData();

        if (!$data) {
            return '';
        }

        return '<script type="application/ld+json">' . $this->serializer->serialize($data) . '</script>';
    }

    abstract public function getJsonData(): ?array;

    abstract protected function getEntityName(): string;

    abstract protected function getEntityImage(): string;

    abstract protected function getEntityDescription(): string;

    abstract protected function getBaseProductCollection(): ProductCollection;

    abstract protected function isRsEnabled(): bool;

    abstract protected function getProductOffersType(): int;

    abstract protected function getProductOffersFormat(): int;

    abstract protected function getDescriptionType(): int;

    abstract protected function getImageConfig(): int;

    abstract protected function getAggregateBrandName(): string;

    abstract protected function isAggregateRatingEnabled(): bool;

    abstract protected function isExcludeZeroPriceProducts(): bool;

    protected function getResult(): array
    {
        if ($this->isAggregateOfferFormat()) {
            return $this->getDataAsAggregateProduct();
        }

        if ($this->getProductOffersType() && $this->isSimpleOfferFormat()
            && $this->getProductCollection() && $this->getProductCollection()->getSize()
        ) {
            return $this->getDataAsItemList();
        }

        return $this->getDataAsOfferCatalog();
    }

    protected function getDataAsOfferCatalog(): array
    {
        $url      = $this->_urlBuilder->escape($this->_urlBuilder->getCurrentUrl());
        $name     = $this->getEntityName();
        $itemList = $this->getProductOffersType() ? $this->getItemList() : [];

        $result = [
            '@context' => Config::HTTP_SCHEMA_ORG,
            '@type'    => 'OfferCatalog',
            'name'     => $name,
            'url'      => $url,
        ];

        $description = $this->getEntityDescription();
        if (!empty($description)) {
            $result['description'] = $description;
        }

        $image = $this->getEntityImage();
        if (!empty($image)) {
            $result['image'] = $image;
        }

        $result['numberOfItems']   = count($itemList) ?: '';
        $result['itemListElement'] = $itemList;

        return $result;
    }

    protected function getDataAsItemList(): array
    {
        $itemList = [];
        $position = 1;

        $collection = $this->getProductCollection();
        if ($collection !== null) {
            foreach ($collection as $product) {
                $data = $this->productSnippetService->getListItemJsonData($product, $position++, true);
                if ($data) {
                    $itemList[] = $data;
                }
            }
        }

        $result = [
            '@context' => Config::HTTP_SCHEMA_ORG,
            '@type'    => 'ItemList',
            'name'     => $this->getEntityName(),
            'url'      => $this->_urlBuilder->escape($this->_urlBuilder->getCurrentUrl()),
        ];

        $description = $this->getEntityDescription();
        if (!empty($description)) {
            $result['description'] = $description;
        }

        $image = $this->getEntityImage();
        if (!empty($image)) {
            $result['image'] = $image;
        }

        $result['numberOfItems']   = count($itemList);
        $result['itemListElement'] = $itemList;

        return $result;
    }

    protected function getDataAsAggregateProduct(): array
    {
        $name        = $this->getEntityName();
        $image       = $this->getEntityImage();
        $description = $this->getEntityDescription();

        if (empty($image)) {
            $image = $this->getFirstProductImage();
        }

        $brandName = $this->getAggregateBrandName();
        if (empty($brandName)) {
            $brandName = $this->_storeManager->getStore()->getName();
        }

        $result = [
            '@context' => Config::HTTP_SCHEMA_ORG,
            '@type'    => 'Product',
            'name'     => $name,
            'brand'    => [
                '@type' => 'Brand',
                'name'  => $brandName,
            ],
        ];

        if (!empty($image)) {
            $result['image'] = $image;
        }

        if (!empty($description)) {
            $result['description'] = $description;
        }

        $aggregateOffer = $this->getAggregateOffer();

        if ($aggregateOffer['offerCount'] > 0) {
            $result['offers'] = $aggregateOffer;
        } elseif (!$this->isExcludeZeroPriceProducts()
            || $this->getBaseProductCollection()->getSize() === 0
        ) {
            return [];
        }

        if ($this->isAggregateRatingEnabled()) {
            $aggregateRating = $this->getAggregateRating();
            if ($aggregateRating) {
                $result['aggregateRating'] = $aggregateRating;
            }
        }

        if (!isset($result['offers']) && !isset($result['aggregateRating'])) {
            return [];
        }

        return $result;
    }

    protected function getFirstProductImage(): string
    {
        $collection = $this->getBaseProductCollection();
        $collection->addAttributeToSelect('image');
        $collection->setPageSize(1);

        $product = $collection->getFirstItem();
        if ($product && $product->getImage() && $product->getImage() !== 'no_selection') {
            return $this->_storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA)
                . 'catalog/product' . $product->getImage();
        }

        return '';
    }

    protected function getAggregateOffer(): array
    {
        $collection = $this->getBaseProductCollection();
        $collection->addFinalPrice();

        $select = $collection->getSelect();

        if ($this->isExcludeZeroPriceProducts()) {
            $select->where('price_index.min_price > 0');
        }

        $select->reset(Select::COLUMNS);
        $select->columns([
            'min_price'   => 'MIN(price_index.min_price)',
            'max_price'   => 'MAX(price_index.max_price)',
            'offer_count' => 'COUNT(*)',
        ]);

        $connection = $collection->getConnection();
        $row        = $connection->fetchRow($select);

        return [
            '@type'         => 'AggregateOffer',
            'lowPrice'      => (float)($row['min_price'] ?? 0),
            'highPrice'     => (float)($row['max_price'] ?? 0),
            'offerCount'    => (int)($row['offer_count'] ?? 0),
            'priceCurrency' => $this->_storeManager->getStore()->getCurrentCurrencyCode(),
            'availability'  => Config::HTTP_SCHEMA_ORG . '/InStock',
        ];
    }

    protected function getAggregateRating(): ?array
    {
        $collection = $this->getBaseProductCollection();

        $select     = $collection->getSelect();
        $connection = $collection->getConnection();

        $select->joinLeft(
            ['review_summary' => $collection->getTable('review_entity_summary')],
            'review_summary.entity_pk_value = e.entity_id AND review_summary.store_id = ' . (int)$this->getStoreId(),
            []
        );

        $select->reset(Select::COLUMNS);
        $select->columns([
            'total_reviews'   => 'SUM(review_summary.reviews_count)',
            'weighted_rating' => 'SUM(review_summary.rating_summary * review_summary.reviews_count)',
        ]);

        $row = $connection->fetchRow($select);

        $totalReviews = (int)($row['total_reviews'] ?? 0);
        if ($totalReviews === 0) {
            return null;
        }

        $weightedRating = (float)($row['weighted_rating'] ?? 0);
        $averageRating  = ($weightedRating / $totalReviews) / 20;

        if ($averageRating <= 0) {
            return null;
        }

        return [
            '@type'       => 'AggregateRating',
            'ratingValue' => round($averageRating, 1),
            'bestRating'  => 5,
            'worstRating' => 1,
            'ratingCount' => $totalReviews,
        ];
    }

    protected function getItemList(): array
    {
        $data = [];

        if ($this->getProductCollection()) {
            foreach ($this->getProductCollection() as $product) {
                $item = $this->productSnippetService->getJsonData($product, true);
                if ($item) {
                    $data[] = $item;
                }
            }
        }

        return $data;
    }

    abstract protected function getProductCollection(): ?ProductCollection;

    protected function getStoreId(): int
    {
        if (!isset($this->storeId)) {
            try {
                $this->storeId = (int)$this->_storeManager->getStore()->getId();
            } catch (NoSuchEntityException $exception) {
                return Store::DEFAULT_STORE_ID;
            }
        }

        return $this->storeId;
    }

    protected function isSimpleOfferFormat(): bool
    {
        return $this->getProductOffersFormat() == AbstractSnippetConfig::PRODUCT_OFFERS_FORMAT_SIMPLE;
    }

    protected function isAggregateOfferFormat(): bool
    {
        return $this->getProductOffersFormat() == AbstractSnippetConfig::PRODUCT_OFFERS_FORMAT_AGGREGATE;
    }

    protected function stripHtmlAndClean(string $value): string
    {
        return $this->htmlCleaner->clean($value);
    }
}
