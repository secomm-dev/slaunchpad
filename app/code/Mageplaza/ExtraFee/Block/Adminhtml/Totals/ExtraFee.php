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
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ExtraFee\Block\Adminhtml\Totals;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\Session\Quote;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Block\Adminhtml\Order\Create\Totals\DefaultTotals;
use Magento\Sales\Helper\Data;
use Magento\Sales\Model\AdminOrder\Create;
use Magento\Sales\Model\Config as SalesConfig;
use Magento\Tax\Model\Config as TaxConfig;

/**
 * Class ExtraFee
 * @package Mageplaza\ExtraFee\Block\Adminhtml\Totals
 */
class ExtraFee extends DefaultTotals
{
    /**
     * Template
     *
     * @var string
     */
    protected $_template = 'totals/extra-fee.phtml';

    /**
     * @var TaxConfig
     */
    protected $_taxConfig;

    /**
     * ExtraFee constructor.
     *
     * @param Context $context
     * @param Quote $sessionQuote
     * @param Create $orderCreate
     * @param PriceCurrencyInterface $priceCurrency
     * @param Data $salesData
     * @param SalesConfig $salesConfig
     * @param TaxConfig $taxConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        Quote $sessionQuote,
        Create $orderCreate,
        PriceCurrencyInterface $priceCurrency,
        Data $salesData,
        SalesConfig $salesConfig,
        TaxConfig $taxConfig,
        array $data = []
    ) {
        $this->_taxConfig = $taxConfig;

        parent::__construct($context, $sessionQuote, $orderCreate, $priceCurrency, $salesData, $salesConfig, $data);
    }

    /**
     * Check if we need display shipping include and exclude tax
     *
     * @return bool
     */
    public function displayBoth()
    {
        return $this->_taxConfig->displayCartShippingBoth();
    }

    /**
     * Check if we need display shipping include tax
     *
     * @return bool
     */
    public function displayIncludeTax()
    {
        return $this->_taxConfig->displayCartShippingInclTax();
    }
}
