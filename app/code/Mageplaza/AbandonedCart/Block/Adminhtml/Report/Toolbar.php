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

namespace Mageplaza\AbandonedCart\Block\Adminhtml\Report;

use Exception;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Mageplaza\AbandonedCart\Helper\Data;
use Mageplaza\AbandonedCart\Model\Behavior;
use Mageplaza\AbandonedCart\Model\OrderSuccessCount;
use Mageplaza\AbandonedCart\Model\ResourceModel\Grid\AbandonedCarts\CollectionFactory as AbandonedCartsCollectionFactory;
use Mageplaza\AbandonedCart\Model\ResourceModel\Grid\ProductReport\CollectionFactory;
use Mageplaza\AbandonedCart\Model\ResourceModel\Logs\CollectionFactory as LogsCollectionFactory;

/**
 * Class Toolbar
 * Mageplaza\AbandonedCart\Block\Adminhtml\Report
 */
class Toolbar extends Template
{
    /**
     * Date model
     *
     * @var DateTime
     */
    protected $date;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var ProductResource
     */
    private $productResource;

    /**
     * @var CollectionFactory
     */
    protected $productReportsCollection;

    /**
     * @var TimezoneInterface
     */
    protected $timezone;

    /**
     * @var ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var Behavior
     */
    protected $behavior;

    /**
     * @var AbandonedCartsCollectionFactory
     */
    protected $abandonedCartsCollectionFactory;

    /**
     * @var LogsCollectionFactory
     */
    protected $logsCollectionFactory;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var OrderSuccessCount
     */
    protected $orderSuccessCount;

    /**
     * Toolbar constructor.
     *
     * @param Context $context
     * @param DateTime $date
     * @param Image $imageHelper
     * @param UrlInterface $urlBuilder
     * @param ProductFactory $productFactory
     * @param ProductResource $productResource
     * @param CollectionFactory $productReportsCollection
     * @param TimezoneInterface $timezone
     * @param ResolverInterface $localeResolver
     * @param Behavior $behavior
     * @param AbandonedCartsCollectionFactory $abandonedCartsCollectionFactory
     * @param LogsCollectionFactory $logsCollectionFactory
     * @param Data $helperData
     * @param OrderSuccessCount $orderSuccessCount
     * @param array $data
     */
    public function __construct(
        Context $context,
        DateTime $date,
        Image $imageHelper,
        UrlInterface $urlBuilder,
        ProductFactory $productFactory,
        ProductResource $productResource,
        CollectionFactory $productReportsCollection,
        TimezoneInterface $timezone,
        ResolverInterface $localeResolver,
        Behavior $behavior,
        AbandonedCartsCollectionFactory $abandonedCartsCollectionFactory,
        LogsCollectionFactory $logsCollectionFactory,
        Data $helperData,
        OrderSuccessCount $orderSuccessCount,
        array $data = []
    ) {
        $this->date                            = $date;
        $this->imageHelper                     = $imageHelper;
        $this->urlBuilder                      = $urlBuilder;
        $this->productFactory                  = $productFactory;
        $this->productResource                 = $productResource;
        $this->productReportsCollection        = $productReportsCollection;
        $this->timezone                        = $timezone;
        $this->localeResolver                  = $localeResolver;
        $this->behavior                        = $behavior;
        $this->abandonedCartsCollectionFactory = $abandonedCartsCollectionFactory;
        $this->logsCollectionFactory           = $logsCollectionFactory;
        $this->helperData                      = $helperData;
        $this->orderSuccessCount               = $orderSuccessCount;

        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getFromDefault()
    {
        if ($fromDate = $this->_request->getParam('from')) {
            return $fromDate;
        }
        $current = $this->date->date('m/d/Y');

        return $this->date->date('m/d/Y', $current . '-15 days');
    }

    /**
     * @return string
     */
    public function getToDefault()
    {
        if ($toDate = $this->_request->getParam('to')) {
            return $toDate;
        }

        return $this->date->date('m/d/Y');
    }

    /**
     * @return string
     */
    public function getApplyUrl()
    {
        return $this->getUrl('abandonedcart/index/report');
    }

    /**
     * @return \Mageplaza\AbandonedCart\Model\ResourceModel\Grid\ProductReport\Collection
     */
    public function getTopProducts()
    {
        $pageSize = 5;

        $collection = $this->productReportsCollection->create();

        $dateRange = $this->getDateRange();
        $from      = $this->date->date('Y-m-d', $dateRange[0]);
        $to        = $this->date->date('Y-m-d', $dateRange[1]);
        if ($from && $to) {
            $collection->addFieldToFilter('period_time', ['gteq' => '"' . $from . '"', 'lteq' => '"' . $to . '"'])
                ->setOrder('abandoned_time', 'desc')
                ->setPageSize($pageSize);
        }

        return $collection;
    }

    /**
     * Get url image product
     *
     * @param $productId
     *
     * @return string
     */
    public function getImage($productId)
    {
        $product = $this->productFactory->create();
        $this->productResource->load($product, $productId);
        $imageHelper = $this->imageHelper->init($product, 'product_listing_thumbnail');

        return $imageHelper->getUrl();
    }

    /**
     * GetStatisticsData
     *
     * @return array
     * @throws Exception
     */
    public function getStatisticsData()
    {
        $collection                  = $this->abandonedCartsCollectionFactory->create();
        $data['recapturableRevenue'] = 0;
        $data['recoveredRevenue']    = 0;
        $totalAbandonedCarts         = 0;
        $actionableAbandonedCarts    = 0;
        $successfulCartsRevenue      = 0;

        foreach ($collection as $item) {
            $data['recapturableRevenue'] += $item->getData('total_abandoned_revenue');
            $data['recoveredRevenue']    += $item->getData('successful_carts_revenue');
            $totalAbandonedCarts         += $item->getData('total_abandoned_carts');
            $actionableAbandonedCarts    += $item->getData('actionable_abandoned_carts');
            $successfulCartsRevenue      += $item->getData('number_of_successful_carts');
        }

        $dateRange         = $this->getDateRange();
        $orderSuccessCount = $this->orderSuccessCount->getSuccessOrderCount($dateRange[0], $dateRange[1]);
        $totalCart         = $totalAbandonedCarts + $orderSuccessCount;

        if ($totalCart !== 0) {
            $data['cartAbandonRate'] = round(100 * $totalAbandonedCarts / $totalCart, 2);
        }
        if (($totalCheckout = $totalAbandonedCarts - $actionableAbandonedCarts + $orderSuccessCount) !== 0) {
            $data['checkoutAbandonmentRate'] = round(100 - 100 * $orderSuccessCount / $totalCheckout, 2);
        } else {
            $data['checkoutAbandonmentRate'] = 0;
        }

        if ($successfulCartsRevenue && $successfulCartsRevenue < $totalAbandonedCarts) {
            $data['recoveredCartRate'] = round(100 * $successfulCartsRevenue / $totalAbandonedCarts, 2);
        } else {
            $data['recoveredCartRate'] = 0;
        }

        $data['totalAbandonedEmails'] = $this->totalAbandonedEmails($dateRange);

        return $data;
    }

    /**
     * @return int
     * @throws Exception
     */
    protected function totalAbandonedEmails($dateRange = null)
    {
        $totalEmail = 0;
        $collection = $this->logsCollectionFactory->create();
        $collection->getSelect()->reset(\Magento\Framework\DB\Select::WHERE);
        $collection->addFieldToFilter('updated_at', ['gteq' => $dateRange[0] . ' 00:00:00'])
            ->addFieldToFilter('updated_at', ['lteq' => $dateRange[1] . ' 23:59:59']);
        foreach ($collection as $item) {
            $totalEmail += $item->getData('sequent_number');
        }

        return $totalEmail;
    }

    /**
     * @return array
     */
    public function getDateRange()
    {
        try {
            $dateRange = $this->helperData->getDateRange();
        } catch (Exception $e) {
            $this->_logger->critical($e->getMessage());
        }
        if ($startDate = $this->getRequest()->getParam('startDate')) {
            $dateRange[0] = $startDate;
        }
        if ($endDate = $this->getRequest()->getParam('endDate')) {
            $dateRange[1] = $endDate;
        }

        return $dateRange;
    }
}
