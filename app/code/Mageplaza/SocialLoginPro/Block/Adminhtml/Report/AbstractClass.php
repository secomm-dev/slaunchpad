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
 * @category  Mageplaza
 * @package   Mageplaza_SocialLoginPro
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\SocialLoginPro\Block\Adminhtml\Report;

use DateTimeZone;
use Exception;
use Magento\Backend\Block\Template;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Mageplaza\SocialLogin\Model\ResourceModel\Social\Collection;
use Mageplaza\SocialLogin\Model\ResourceModel\Social\CollectionFactory;
use Mageplaza\SocialLoginPro\Helper\Data as HelperData;

/**
 * Class AbstractClass
 * @package Mageplaza\SocialLoginPro\Block\Adminhtml\Report
 */
abstract class AbstractClass extends Template
{
    const NAME              = '';
    const MAGE_REPORT_CLASS = '';

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var CollectionFactory
     */
    protected $_socialCollFact;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * @var DateTime
     */
    protected $_dateTime;

    /**
     * AbstractClass constructor.
     *
     * @param Template\Context $context
     * @param PriceCurrencyInterface $priceCurrency
     * @param CollectionFactory $socialCollFact
     * @param HelperData $helperData
     * @param DateTime $dateTime
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        PriceCurrencyInterface $priceCurrency,
        CollectionFactory $socialCollFact,
        HelperData $helperData,
        DateTime $dateTime,
        array $data = []
    ) {
        $this->setArea('adminhtml');
        $this->priceCurrency   = $priceCurrency;
        $this->_socialCollFact = $socialCollFact;
        $this->_helperData     = $helperData;
        $this->_dateTime       = $dateTime;

        parent::__construct($context, $data);
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function getContentHtml()
    {
        if (static::MAGE_REPORT_CLASS) {
            return $this->getLayout()->createBlock(static::MAGE_REPORT_CLASS)->setArea('adminhtml')
                ->toHtml();
        }

        return $this->toHtml();
    }

    /**
     * @param $collection
     * @param $dateCol
     *
     * @return mixed
     * @throws Exception
     */
    public function addDateFilter($collection, $dateCol)
    {
        $dateRange = $this->getDateRange();
        $start     = $this->convertTimeZone($dateRange[0])->format('Y-m-d H:i:s');
        $end       = $this->convertTimeZone($dateRange[1])->format('Y-m-d H:i:s');
        $collection->addFieldToFilter($dateCol, ['from' => $start]);
        $collection->addFieldToFilter($dateCol, ['to' => $end]);

        return $collection;
    }

    /**
     * @return array
     */
    public function getDateRange()
    {
        if ($dateRange = $this->_request->getParam('dateRange')) {
            $fromDate = $this->_dateTime->date('m/d/Y', $dateRange[0]);
            $toDate   = $this->_dateTime->date('m/d/Y', $dateRange[1]);
        } else {
            $toDate   = $this->_dateTime->date('m/d/Y');
            $fromDate = $this->_dateTime->date('m/d/Y', $toDate . '-1 month');
        }

        return [$fromDate, $toDate];
    }

    /**
     * @param string $date
     *
     * @return \DateTime
     * @throws Exception
     */
    public function convertTimeZone($date)
    {
        $dateTime = new \DateTime($date, new DateTimeZone('UTC'));
        $dateTime->setTimezone(new DateTimeZone($this->_localeDate->getConfigTimezone()));

        return $dateTime;
    }

    /**
     * @return mixed
     */
    public function getCollection()
    {
        return $this->_socialCollFact->create();
    }

    /**
     * @param $type
     *
     * @return Collection
     */
    public function filterOrder($type)
    {
        /** @var Collection $collection */
        $collection = $this->getCollection();
        $collection->filterOrder($type);

        return $collection;
    }

    /**
     * @param $type
     *
     * @return int
     * @throws Exception
     */
    public function getNumberUser($type)
    {
        $collection = $this->getCollection();
        $collection->addFieldToFilter('type', $type);
        $this->addDateFilter($collection, 'main_table.social_created_at');

        return $collection->getSize();
    }

    /**
     * @return array
     */
    public function getSocialTypesArray()
    {
        return $this->_helperData->getSocialTypesArray();
    }

    /**
     * @param $key
     *
     * @return string
     */
    public function getBtnKey($key)
    {
        return $key === 'vkontakte' ? 'vk' : $key;
    }

    /**
     * @return bool
     */
    public function canShowDetail()
    {
        return false;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return '';
    }

    /**
     * @return string
     */
    public function getName()
    {
        return static::NAME;
    }

    /**
     * @return string
     */
    public function getTotal()
    {
        return '';
    }

    /**
     * @param $price
     *
     * @return float
     */
    public function formatPrice($price)
    {
        return $this->priceCurrency->format($price, true, PriceCurrencyInterface::DEFAULT_PRECISION);
    }
}
