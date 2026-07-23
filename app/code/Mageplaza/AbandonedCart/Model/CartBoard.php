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
 * @package     Mageplaza_AbandonedCart
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\AbandonedCart\Model;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime as StdlibDateTime;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Quote\Model\ResourceModel\Quote\Collection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollection;
use Magento\Store\Model\Store;
use Magento\Framework\View\Asset\Repository;
use Mageplaza\AbandonedCart\Helper\Data;
use Mageplaza\AbandonedCart\Model\ResourceModel\ProductReport;
use Psr\Log\LoggerInterface;
use Magento\Framework\Model\ResourceModel\IteratorFactory;

/**
 * Class CartBoard
 *
 * @package Mageplaza\AbandonedCart\Model
 */
class CartBoard
{
    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var QuoteResource
     */
    private $quoteResource;

    /**
     * @var QuoteCollection
     */
    private $quoteCollection;

    /**
     * @var Data
     */
    private $helperData;

    /**
     * @var array
     */
    protected $dateRange = ['from' => null, 'to' => null];

    /**
     * @var Store
     */
    private $storeManager;

    /**
     * @var CurrencyFactory
     */
    private $currencyFactory;

    /**
     * @var StdlibDateTime
     */
    private $time;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var ProductResource
     */
    private $productResource;

    /**
     * @var ProductReport
     */
    private $productReport;

    /**
     * @var Repository
     */
    private $assetRepository;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var array
     */
    private $realtime = [];

    /**
     * @var array
     */
    private $abandoned = [];

    /**
     * @var array
     */
    private $recoverable = [];

    /**
     * @var array
     */
    private $converted = [];

    /**
     * CartBoard constructor.
     *
     * @param QuoteFactory $quoteFactory
     * @param QuoteResource $quoteResource
     * @param QuoteCollection $quoteCollection
     * @param Data $helperData
     * @param Store $storeManager
     * @param CurrencyFactory $currencyFactory
     * @param StdlibDateTime $time
     * @param LoggerInterface $logger
     * @param ProductFactory $productFactory
     * @param ProductResource $productResource
     * @param ProductReport $productReport
     * @param IteratorFactory $iteratorFactory
     */
    public function __construct(
        QuoteFactory $quoteFactory,
        QuoteResource $quoteResource,
        QuoteCollection $quoteCollection,
        Data $helperData,
        Store $storeManager,
        CurrencyFactory $currencyFactory,
        StdlibDateTime $time,
        LoggerInterface $logger,
        ProductFactory $productFactory,
        ProductResource $productResource,
        ProductReport $productReport,
        Repository $repository,
        IteratorFactory $iteratorFactory
    ) {
        $this->quoteFactory    = $quoteFactory;
        $this->quoteResource   = $quoteResource;
        $this->quoteCollection = $quoteCollection;
        $this->helperData      = $helperData;
        $this->storeManager    = $storeManager;
        $this->currencyFactory = $currencyFactory;
        $this->time            = $time;
        $this->logger          = $logger;
        $this->productFactory  = $productFactory;
        $this->productResource = $productResource;
        $this->productReport   = $productReport;
        $this->assetRepository = $repository;
        $this->iteratorFactory = $iteratorFactory;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getData()
    {
        $storeId     = $this->helperData->getStoreFilter() ?: null;
        $timeMeasure = $this->helperData->getRealtimeConfig('time_measure', $storeId);
        $iterator    = $this->iteratorFactory->create();

        [$limit, $page] = $this->helperData->getPaginationFilter();
        // get realtime quote
        $iterator->walk($this->getQuotesIsActive($timeMeasure, '>=')->getSelect(), [[$this, 'getRealtime']]);

        // get abandoned quote
        $iterator->walk($this->getQuotesIsActive($timeMeasure, '<=', false)->getSelect(), [[$this, 'getAbandoned']]);

        // get recoverable quote
        $iterator->walk($this->getQuotesIsActive($timeMeasure, '<=', true)->getSelect(), [[$this, 'getRecoverable']]);

        // get recoverable quote
        $iterator->walk($this->filterDateRange(0)->getSelect(), [[$this, 'getConverted']]);

        //calculate total pages
        $max        = max(array_map('count',
            [$this->realtime, $this->abandoned, $this->recoverable, $this->converted]));
        $totalPages = ceil($max / $limit);
        $page       = min($page, $totalPages);
        $pageData   = [
            'currentPage' => $page,
            'totalPage'   => $totalPages
        ];

        //update record per page
        $this->realtime    = array_slice($this->realtime, ($page - 1) * $limit, $limit);
        $this->abandoned   = array_slice($this->abandoned, ($page - 1) * $limit, $limit);
        $this->recoverable = array_slice($this->recoverable, ($page - 1) * $limit, $limit);
        $this->converted   = array_slice($this->converted, ($page - 1) * $limit, $limit);

        try {
            $data = [
                'realtime'    => $this->realtime,
                'abandoned'   => $this->abandoned,
                'recoverable' => $this->recoverable,
                'converted'   => $this->converted,
                'pageData'    => $pageData
            ];

        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        return $data;
    }

    /**
     * @param $args
     *
     * @return void
     * @throws LocalizedException
     */
    public function getAbandoned($args)
    {
        $this->getCartData($args, $this->abandoned);
    }

    /**
     * @param $args
     *
     * @return void
     * @throws LocalizedException
     */
    public function getRecoverable($args)
    {
        $this->getCartData($args, $this->recoverable);
    }

    /**
     * @param $args
     *
     * @return void
     * @throws LocalizedException
     */
    public function getConverted($args)
    {
        $this->getCartData($args, $this->converted);
    }

    /**
     * @param $args
     *
     * @return void
     * @throws LocalizedException
     */
    public function getRealtime($args)
    {
        $this->getCartData($args, $this->realtime);
    }

    /**
     * @param int $isActive
     * @param null $from
     * @param null $toD
     *
     * @return Collection
     * @throws Exception
     */
    private function filterDateRange($isActive, $from = null, $toD = null)
    {
        try {
            $dateRangeFilter = $this->helperData->getDateRangeFilter($from, $toD);
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
        $from = $dateRangeFilter[0];
        $toD  = $dateRangeFilter[1];

        $quoteCollection = $this->quoteCollection->create();
        /** @var  Collection $quotes */
        $quotes = $quoteCollection->addFieldToFilter('is_active', $isActive)
            ->addFieldToFilter('items_count', ['gt' => 0])
            ->setOrder('updated_at');

        $store = $this->helperData->getStoreFilter();

        if ($store) {
            $quotes->addFieldToFilter('store_id', ['eq' => $store]);
        }
        $connection = $quoteCollection->getConnection();
        $dateTime   = $connection->getDatePartSql($this->productReport->getStoreTZOffsetQuery(
            ['main_table' => $quotes->getMainTable()],
            'main_table.updated_at',
            $from,
            $toD
        ));

        if ($from !== null) {
            $quotes->addFieldToFilter($dateTime, ['gteq' => $from]);
        }
        if ($toD !== null) {
            $quotes->addFieldToFilter($dateTime, ['lteq' => $toD]);
        }

        return $quotes;
    }

    /**
     * @param array $args
     *
     * @return void
     * @throws LocalizedException
     */
    public function getCartData($args, &$type)
    {
        $quoteData = $args['row'];

        $quote_id         = $quoteData['entity_id'];
        $store_id         = $quoteData['store_id'];
        $email            = $quoteData['customer_email'];
        $firstname        = $quoteData['customer_firstname'];
        $base_grand_total = $quoteData['base_grand_total'];
        $update_at        = $quoteData['updated_at'];

        $productImages = [];
        foreach ($this->getProductCollection($quote_id) as $itemCollection) {
            $productImages[] = $this->getProductImage($itemCollection, $store_id);
        }

        $type[] = [
            'customerEmail'  => $email,
            'customerName'   => $firstname,
            'total'          => $this->getCurrentCurrencySymbol($store_id) .
                round($base_grand_total, 2),
            'differenceTime' => $this->getDifferenceTime($update_at),
            'productImages'  => $productImages
        ];
    }

    /**
     * @param string $timeMeasure
     * @param string $operator
     * @param null|bool $hasCustomerEmail
     *
     * @return Collection
     * @throws Exception
     */
    protected function getQuotesIsActive($timeMeasure, $operator, $hasCustomerEmail = null)
    {
        $quotes = $this->filterDateRange(1);
        if ($hasCustomerEmail === false) {
            $quotes->addFieldToFilter('customer_email', ['null' => true]);
        }

        if ($hasCustomerEmail === true) {
            $quotes->addFieldToFilter('customer_email', ['notnull' => true]);
        }
        if ($timeMeasure) {
            $quotes->getSelect()->joinLeft(
                ['customer_log' => $quotes->getTable('customer_log')],
                'main_table.customer_id = customer_log.customer_id',
                []
            )->where("IF(customer_log.last_login_at IS NULL , updated_at, customer_log.last_login_at )
            {$operator} DATE_SUB(NOW(), INTERVAL '  {$timeMeasure} ' MINUTE)");
        }

        return $quotes;
    }

    /**
     * @param $quoteId
     *
     * @return array|Item[]
     */
    public function getProductCollection($quoteId)
    {
        $items = [];

        $quote = $this->quoteFactory->create();
        $this->quoteResource->load($quote, $quoteId);
        if ($quote) {
            return $quote->getAllVisibleItems();
        }

        return $items;
    }

    /**
     * @param Item $item
     * @param int $storeId
     *
     * @return string|string[]
     * @throws LocalizedException
     */
    public function getProductImage($item, $storeId)
    {
        $product = $this->productFactory->create();
        if ($item->getProductType() === 'configurable') {
            $productId = $product->getIdBySku($item->getSku());
        } else {
            $productId = $item->getProductId();
        }

        /** @var  Product $product */
        $product = $this->productFactory->create();
        $this->productResource->load($product, $productId);
        /** @var Store $store */
        $store        = $this->storeManager->load($storeId);
        $productImage = $product->getImage();

        if ($productImage) {
            $imageUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $productImage;
        } else {
            $imageUrl = $this->assetRepository->getUrl(
                'Magento_Catalog::images/product/placeholder/small_image.jpg'
            );
        }

        return str_replace('\\', '/', $imageUrl);
    }

    /**
     * @param string $updatedTime
     *
     * @return string
     */
    protected function getDifferenceTime($updatedTime)
    {
        $time = $this->time->date();
        $diff = date_diff(date_create($updatedTime), date_create($time));

        return $diff->format('%d days,%H hours,%i minutes ago');
    }

    /**
     * @param int $storeId
     *
     * @return string
     * @throws LocalizedException
     */
    protected function getCurrentCurrencySymbol($storeId)
    {
        $currencyCode = $this->storeManager->load($storeId)->getBaseCurrencyCode();

        return $this->currencyFactory->create()->load($currencyCode)->getCurrencySymbol();
    }
}
