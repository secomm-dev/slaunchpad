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

namespace Mageplaza\AbandonedCart\Ui\Component\DataProvider;

use Magento\Directory\Model\Currency;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider as AbstractProvider;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\AbandonedCart\Helper\Data;

/**
 * Class DataProvider
 * @package Mageplaza\AbandonedCart\Ui\Component\DataProvider
 */
class LogsProvider extends AbstractProvider
{
    /**
     * @var Currency
     */
    protected $baseCurrency;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Date model
     *
     * @var DateTime
     */
    protected $date;

    /**
     * @var Data
     */
    protected $_helperData;

    /**
     * LogsProvider constructor.
     *
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param StoreManagerInterface $storeManager
     * @param DateTime $date
     * @param Data $helperData
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        StoreManagerInterface $storeManager,
        DateTime $date,
        Data $helperData,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );

        $this->storeManager = $storeManager;
        $this->date         = $date;
        $this->_helperData  = $helperData;
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getData()
    {
        $this->addFilter(
            $this->filterBuilder->setField('display')->setValue(1)->setConditionType('eq')->create()
        );

        $params = $this->request->getParam('mpFilter');
        $dateRange = $this->_helperData->getDateRange();
        $from = ($this->getParam($params, 'startDate') === "") ? $this->getParam($params, 'startDate'): $dateRange[0];
        $to = ($this->getParam($params, 'endDate') === "") ? $this->getParam($params, 'endDate'): $dateRange[1];

        if (!$this->request->isAjax()) {
            $from = $this->request->getParam('startDate') ? $this->request->getParam('startDate'): $dateRange[0];
            $to = $this->request->getParam('endDate') ? $this->request->getParam('endDate') : $dateRange[1];
        }

        $from = $from." 00:00:00";
        $to   = $to." 23:59:59";

        $this->addFilter(
            $this->filterBuilder->setField('updated_at')
                ->setValue($from)
                ->setConditionType('gteq')
                ->create()
        );

        $this->addFilter(
            $this->filterBuilder->setField('updated_at')
                ->setValue($to)
                ->setConditionType('lteq')
                ->create()
        );


        $data = $this->searchResultToOutput($this->getSearchResult());

        return $data;
    }

    /**
     * Retrieve param by key
     *
     * @param $params
     * @param string $key
     *
     * @return mixed
     */
    public function getParam($params, $key)
    {
        if (isset($params[$key])) {
            return $params[$key];
        }

        return null;
    }
}
