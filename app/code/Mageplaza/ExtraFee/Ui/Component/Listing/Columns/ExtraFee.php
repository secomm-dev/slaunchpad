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

namespace Mageplaza\ExtraFee\Ui\Component\Listing\Columns;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Ui\Component\Listing\Columns\Column;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class ExtraFee
 * @package Mageplaza\ExtraFee\Ui\Component\Listing\Columns
 */
class ExtraFee extends Column
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var TaxHelper
     */
    protected $taxHelper;

    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * ExtraFee constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param Data $helperData
     * @param PriceCurrencyInterface $priceCurrency
     * @param TaxHelper $taxHelper
     * @param OrderRepository $orderRepository
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        Data $helperData,
        PriceCurrencyInterface $priceCurrency,
        TaxHelper $taxHelper,
        OrderRepository $orderRepository,
        array $components = [],
        array $data = []
    ) {
        $this->helperData      = $helperData;
        $this->priceCurrency   = $priceCurrency;
        $this->taxHelper       = $taxHelper;
        $this->orderRepository = $orderRepository;

        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @throws LocalizedException
     */
    public function prepare()
    {
        $config     = $this->getData('config');
        $showInGrid = $this->helperData->getConfigGeneral('enabled_order_grid');

        if (!$showInGrid || !$this->helperData->isEnabled()) {
            $config['componentDisabled'] = true;
        }

        $this->setData('config', $config);

        parent::prepare();
    }

    /**
     * @inheritdoc
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items']) && $this->helperData->isEnabled()
            && $this->helperData->getConfigGeneral('enabled_order_grid')) {
            foreach ($dataSource['data']['items'] as &$item) {
                $extraFee       = isset($item['mp_extra_fee']) ? Data::jsonDecode($item['mp_extra_fee']) : [];
                $extraFeeTotals = isset($extraFee['totals']) ? $extraFee['totals'] : [];
                $extraFeeInfo   = '';
                if ($extraFeeTotals) {
                    foreach ($extraFeeTotals as $extraFeeTotal) {
                        $extraFeeInfo .= $this->getFeeTitle($extraFeeTotal, $item['entity_id']) . '<br>';
                    }
                }

                $item['mp_extra_fee'] = $extraFeeInfo;
            }
        }

        return $dataSource;
    }

    /**
     * @param array $fee
     * @param int $orderId
     *
     * @return string
     */
    public function getFeeTitle($fee, $orderId)
    {
        try {
            $result = "<strong>{$fee['rule_label']}"
                . ((strpos($fee['code'], 'auto') === false)
                    ? " - {$fee['label']}" : $fee['label']) . ' </strong>';
            $order  = $this->orderRepository->get($orderId);

            $baseRuleFeeAmount        = $this->priceCurrency->format(
                $fee['base_value'],
                true,
                2,
                null,
                $order->getBaseCurrency()
            );
            $baseRuleFeeAmountInclTax = $this->priceCurrency->format(
                $fee['base_value_incl_tax'],
                true,
                2,
                null,
                $order->getBaseCurrency()
            );
            $ruleFeeAmount            = $this->priceCurrency->format(
                $fee['value_excl_tax'],
                true,
                2,
                null,
                $order->getOrderCurrency()
            );
            $ruleFeeAmountInclTax     = $this->priceCurrency->format(
                $fee['value_incl_tax'],
                true,
                2,
                null,
                $order->getOrderCurrency()
            );

            if ($this->taxHelper->displayShippingPriceIncludingTax()) {
                $excl = "<strong>{$baseRuleFeeAmountInclTax}</strong> [{$ruleFeeAmountInclTax}] ";
            } else {
                $excl = "<strong>{$baseRuleFeeAmount}</strong> "
                    . ($order->isCurrencyDifferent() ? "[{$ruleFeeAmount}] " : '');
            }
            $incl   = "<strong>{$baseRuleFeeAmountInclTax}</strong> "
                . ($order->isCurrencyDifferent() ? "[{$ruleFeeAmountInclTax}] " : '');
            $result .= $excl;
            if ($incl !== $excl && $this->taxHelper->displayShippingBothPrices()) {
                $result .= ' (' . __('Incl. Tax ') . $incl . ')';
            }

            return $result;
        } catch (Exception $e) {
            return '';
        }
    }
}
