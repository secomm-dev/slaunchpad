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
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Block\Request\Index;

use Exception;
use Magento\Directory\Model\Currency;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Item\Collection as ItemCollection;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\Config\Source\RMARequest\ReturnType;

/**
 * Class Items
 * @method Items setOrderedProducts($itemCollection)
 * @method Items setOrder($order)
 * @method ItemCollection getOrderedProducts()
 * @method Order getOrder()
 * @package Mageplaza\RMA\Block\Request
 */
class Items extends Template
{
    /**
     * @var HelperData
     */
    public $_helperData;

    /**
     * @var ReturnType
     */
    protected $_returnType;

    /**
     * @var Currency
     */
    protected $_currency;

    /**
     * Items constructor.
     *
     * @param Context $context
     * @param HelperData $helperData
     * @param ReturnType $returnType
     * @param Currency $currency
     * @param array $data
     */
    public function __construct(
        Context $context,
        HelperData $helperData,
        ReturnType $returnType,
        Currency $currency,
        array $data = []
    ) {
        $this->_helperData = $helperData;
        $this->_returnType = $returnType;
        $this->_currency = $currency;

        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function isReturnEachItem()
    {
        return $this->_helperData->getRequestConfig('each_item');
    }

    /**
     * @return array
     */
    public function getReturnTypes()
    {
        return $this->_returnType->toOptionArray();
    }

    /**
     * @param $price
     * @param $currencyCode
     * @return float
     * @throws Exception
     */
    public function getCurrencyPrice($price, $currencyCode)
    {
        $currency = $this->_currency->load('USD');
        return $currency->convert($price, $currencyCode);
    }
}
