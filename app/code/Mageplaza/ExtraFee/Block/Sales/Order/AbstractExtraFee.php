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

namespace Mageplaza\ExtraFee\Block\Sales\Order;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order;
use Magento\Tax\Helper\Data as TaxHelper;
use Mageplaza\ExtraFee\Helper\Data;
use Mageplaza\ExtraFee\Model\Config\Source\DisplayArea;

/**
 * Class AbstractExtraFee
 * @package Mageplaza\ExtraFee\Block\Sales\Order
 */
abstract class AbstractExtraFee extends Template
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var TaxHelper
     */
    protected $taxHelper;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var Order
     */
    protected $order;

    /**
     * AbstractExtraFee constructor.
     *
     * @param Template\Context $context
     * @param Registry $registry
     * @param PriceCurrencyInterface $priceCurrency
     * @param TaxHelper $taxHelper
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Registry $registry,
        PriceCurrencyInterface $priceCurrency,
        TaxHelper $taxHelper,
        Data $helper,
        array $data = []
    ) {
        $this->registry      = $registry;
        $this->priceCurrency = $priceCurrency;
        $this->taxHelper     = $taxHelper;
        $this->helper        = $helper;

        parent::__construct($context, $data);
    }

    /**
     * @param string $area
     *
     * @return array
     */
    public function getExtraFeeInfo($area)
    {
        /** @var Order $order */
        $order = $this->getOrder();
        $order->getBaseCurrency();
        $extraFeeTotals = $this->helper->getExtraFeeTotals($order);
        $actionName     = $this->getRequest()->getActionName();
        $creditmemo     = [];
        if (($this->registry->registry('current_invoice')
            && $this->registry->registry('current_invoice')->getItems())) {
            $invoice        = $this->registry->registry('current_invoice');
            $extraFeeTotals = $this->helper->getObjectExtraFeeTotals($invoice, $order);
        }
        if (($this->registry->registry('current_creditmemo')
            && $this->registry->registry('current_creditmemo')->getItems())) {
            $creditmemo     = $this->registry->registry('current_creditmemo');
            $extraFeeTotals = $this->helper->getObjectExtraFeeTotals($creditmemo, $order);
        }
        if (($this->registry->registry('current_shipment')
            && $this->registry->registry('current_shipment')->getItems())) {
            $shipment       = $this->registry->registry('current_shipment');
            $extraFeeTotals = $this->helper->getObjectExtraFeeTotals($shipment, $order);
        }
        $result = [];
        foreach ($extraFeeTotals as $fee) {
            if ((int) $fee['display_area'] === $area) {
                if(($actionName === 'creditmemo' || !empty($creditmemo)) && $fee['rf'] != 1) {
                    continue;
                }
                $result[] = $fee;
            }
        }

        return $result;
    }

    /**
     * @param array $fee
     *
     * @return string
     */
    public function getFeeTitle($fee)
    {
        $result = "<strong>{$this->escapeHtml($fee['rule_label'])}"
            . (($fee['label'] && strpos($fee['code'], 'auto') === false)
                ? " - {$this->escapeHtml($fee['label'])}" : '') . ' </strong>';
        $order  = $this->getOrder();

        $baseRuleFeeAmount        =
            $this->priceCurrency->format($fee['base_value'], true, 2, null, $order->getBaseCurrency());
        $baseRuleFeeAmountInclTax =
            $this->priceCurrency->format($fee['base_value_incl_tax'], true, 2, null, $order->getBaseCurrency());
        $ruleFeeAmount            =
            $this->priceCurrency->format($fee['value_excl_tax'], true, 2, null, $order->getOrderCurrency());
        $ruleFeeAmountInclTax     =
            $this->priceCurrency->format($fee['value_incl_tax'], true, 2, null, $order->getOrderCurrency());
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
            $result .= ' (' . $this->escapeHtml(__('Incl. Tax ')) . $incl . ')';
        }

        return $result;
    }

    /**
     * @param array $fee
     *
     * @return string
     */
    public function getFrontendTitle($fee)
    {
        return $fee['rule_label'] . ($fee['label'] ? " - {$fee['label']}" : '');
    }

    /**
     * @return array
     */
    public function getFrontendExtraFeeInfo()
    {
        $extraFeeTotals = $this->getExtraFeeInfo(DisplayArea::CART_SUMMARY);
        $result         = [];
        foreach ($extraFeeTotals as $fee) {
            $result[$fee['rule_label']][] = $fee;
        }

        return $result;
    }

    /**
     * @return mixed
     */
    protected function getOrder()
    {
        if (!$this->order) {
            $this->order = $this->registry->registry('current_order');
        }

        return $this->order;
    }

    /**
     * @return mixed
     */
    public function getCurrentOrder()
    {
        return $this->getOrder();
    }

    /**
     * @param $area
     *
     * @return array
     */
    public function getExtraFeeNoteInfo($area)
    {
        $extraFeeData = $this->getExtraFeeInfo($area);
        $ids          = [];
        $title        = [];

        foreach ($extraFeeData as $data) {
            $id         = array_filter(preg_split("/\D+/", $data['code']));
            $id         = reset($id);
            $title[$id] = $data['rule_label'];
            $ids[]      = $id;
        }

        return [array_unique($ids), $title];
    }

    /**
     * @param $area
     *
     * @return array
     */
    public function getExtraFeeNote($area)
    {
        [$ids, $title] = $this->getExtraFeeNoteInfo($area);

        $extraFee = $this->helper->unserialize($this->getOrder()->getMpExtraFee());

        $note = [];

        if (isset($extraFee['note'])) {
            foreach ($ids as $id) {
                $key = 'mp-extrafee-note-' . $id;
                foreach ($extraFee['note'] as $index => $value) {
                    if (str_contains($index, $key)) {
                        $note[$title[$id]] = $value;
                    }
                }
            }

            return $note;
        }

        switch ($area) {
            case DisplayArea::CART_SUMMARY:
                if ($extraFee && isset($extraFee['summary'])) {
                    $summaryArray = explode('&', $extraFee['summary']);
                    foreach ($ids as $id) {
                        $key = 'mp-extrafee-note-' . $id;
                        foreach ($summaryArray as $value) {
                            $content = substr($value, strpos($value, '=') + 1);
                            if (str_contains($value, $key) && $content != '') {
                                $note[$title[$id]] = substr($value, strpos($value, '=') + 1);
                            }
                        }
                    }
                }
                break;
            case DisplayArea::SHIPPING_METHOD:
                if ($extraFee && isset($extraFee['shipping'])) {
                    $array = explode('&', $extraFee['shipping']);
                    foreach ($ids as $id) {
                        $key = 'mp-extrafee-note-' . $id;
                        foreach ($array as $value) {
                            $content = substr($value, strpos($value, '=') + 1);
                            if (str_contains($value, $key) && $content != '') {
                                $note[$title[$id]] = substr($value, strpos($value, '=') + 1);
                            }
                        }
                    }
                }
                break;
            case DisplayArea::PAYMENT_METHOD:
                if ($extraFee && isset($extraFee['payment'])) {
                    $array = explode('&', $extraFee['payment']);
                    foreach ($ids as $id) {
                        $key = 'mp-extrafee-note-' . $id;
                        foreach ($array as $value) {
                            $content = substr($value, strpos($value, '=') + 1);
                            if (str_contains($value, $key) && $content != '') {
                                $note[$title[$id]] = substr($value, strpos($value, '=') + 1);
                            }
                        }
                    }
                }
                break;
        }

        return $note;
    }
}
