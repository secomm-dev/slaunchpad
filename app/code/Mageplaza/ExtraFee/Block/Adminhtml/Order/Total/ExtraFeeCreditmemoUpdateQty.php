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

namespace Mageplaza\ExtraFee\Block\Adminhtml\Order\Total;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Registry;
use Magento\Sales\Block\Adminhtml\Order\Creditmemo\Create\Items;
use Magento\Sales\Helper\Data;
use Mageplaza\ExtraFee\Block\Sales\Order\Creditmemo\Create\ExtraFee;
use Mageplaza\ExtraFee\Helper\Data as ExtraFeeData;

/**
 * Class ExtraFeeCreditmemoUpdateQty
 * @package Mageplaza\ExtraFee\Block\Adminhtml\Order\Total
 */
class ExtraFeeCreditmemoUpdateQty extends Items
{
    /**
     * @var ExtraFeeData
     */
    protected $extraFeeData;

    /**
     * ExtraFeeCreditmemoUpdateQty constructor.
     *
     * @param Context $context
     * @param StockRegistryInterface $stockRegistry
     * @param StockConfigurationInterface $stockConfiguration
     * @param Registry $registry
     * @param Data $salesData
     * @param ExtraFeeData $extraFeeData
     * @param array $data
     */
    public function __construct(
        Context $context,
        StockRegistryInterface $stockRegistry,
        StockConfigurationInterface $stockConfiguration,
        Registry $registry,
        Data $salesData,
        ExtraFeeData $extraFeeData,
        array $data = []
    ) {
        $this->extraFeeData = $extraFeeData;

        parent::__construct($context, $stockRegistry, $stockConfiguration, $registry, $salesData, $data);
    }

    /**
     * @return string
     */
    protected function _toHtml()
    {
        $html = parent::_toHtml();

        try {
            if ($this->extraFeeData->isEnabled()) {
                $html .= $this->getLayout()->createBlock(ExtraFee::class)
                    ->setTemplate('Mageplaza_ExtraFee::order/creditmemo/create/extra-fee.phtml')->toHtml();
            }
        } catch (Exception $e) {
            $html .= '';
        }

        return $html;
    }
}
