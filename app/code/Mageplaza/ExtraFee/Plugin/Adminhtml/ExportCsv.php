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

namespace Mageplaza\ExtraFee\Plugin\Adminhtml;

use Closure;
use Exception;
use Magento\Framework\Api\Search\DocumentInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Ui\Model\Export\MetadataProvider;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class ExportCsv
 * @package Mageplaza\ExtraFee\Plugin\Adminhtml
 */
class ExportCsv
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var TaxHelper
     */
    protected $taxHelper;

    /**
     * @var Order
     */
    protected $orderRepository;

    /**
     * ExportCsv constructor.
     *
     * @param Data $helperData
     * @param RequestInterface $request
     * @param PriceCurrencyInterface $priceCurrency
     * @param TaxHelper $taxHelper
     * @param Order $orderRepository
     */
    public function __construct(
        Data $helperData,
        RequestInterface $request,
        PriceCurrencyInterface $priceCurrency,
        TaxHelper $taxHelper,
        Order $orderRepository
    ) {
        $this->helperData      = $helperData;
        $this->request         = $request;
        $this->priceCurrency   = $priceCurrency;
        $this->taxHelper       = $taxHelper;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param MetadataProvider $subject
     * @param Closure $proceed
     * @param DocumentInterface $document
     * @param array $fields
     * @param array $options
     *
     * @return mixed|string
     */
    public function aroundGetRowData(
        MetadataProvider $subject,
        Closure $proceed,
        DocumentInterface $document,
        $fields,
        $options
    ) {
        $namespace    = $this->request->getParam('namespace');
        $namespaces   = [
            'sales_order_grid',
            'mageplaza_extrafee_managerules_listing'
        ];
        $showExtraFee = $this->helperData->getConfigGeneral('enabled_order_grid');

        if ($this->helperData->isEnabled() && in_array($namespace, $namespaces, true)) {
            $row = [];
            foreach ($fields as $k => $column) {
                if (!$showExtraFee && $column === 'mp_extra_fee') {
                    unset($fields[$k]);
                    continue;
                }
                if (isset($options[$column])) {
                    $key = $document->getCustomAttribute($column)->getValue();
                    if (isset($options[$column][$key])) {
                        $row[] = $options[$column][$key];
                    } else {
                        if ($column === 'area' && !$key) {
                            $row[] = __('Auto');
                        } else {
                            $row[] = $key;
                        }
                    }
                } else {
                    if ($showExtraFee && $column === 'mp_extra_fee'
                        && $extraFees = $document->getCustomAttribute($column)->getValue()) {
                        $orderIncrementId = $document->getCustomAttribute('increment_id')->getValue();
                        $extraFee         = Data::jsonDecode($extraFees);
                        $extraFeeTotals   = isset($extraFee['totals']) ? $extraFee['totals'] : [];
                        $extraFeeInfo     = '';
                        $i                = 1;
                        if ($extraFeeTotals) {
                            foreach ($extraFeeTotals as $extraFeeTotal) {
                                $i++;
                                $extraFeeInfo .= $this->getFeeTitle($extraFeeTotal, $orderIncrementId)
                                    . ((count($extraFeeTotals) >= $i) ? ', ' : '');
                            }
                        }
                        $row[] = $extraFeeInfo;
                    } else {
                        $row[] = $document->getCustomAttribute($column)->getValue();
                    }
                }
            }

            return $row;
        }

        return $proceed($document, $fields, $options);
    }

    /**
     * @param array $fee
     * @param int $orderIncrementId
     *
     * @return string
     */
    public function getFeeTitle($fee, $orderIncrementId)
    {
        try {
            $result = "{$fee['rule_label']}"
                . ((strpos($fee['code'], 'auto') === false)
                    ? " - {$fee['label']}" : $fee['label']) . ':';
            $order  = $this->orderRepository->loadByIncrementId($orderIncrementId);

            $baseRuleFeeAmount        = $fee['base_value'];
            $baseRuleFeeAmountInclTax = $fee['base_value_incl_tax'];
            $ruleFeeAmount            = $fee['value_excl_tax'];
            $ruleFeeAmountInclTax     = $fee['value_incl_tax'];

            if ($this->taxHelper->displayShippingPriceIncludingTax()) {
                $excl = " {$baseRuleFeeAmountInclTax}[{$ruleFeeAmountInclTax}] ";
            } else {
                $excl = " {$baseRuleFeeAmount} " . ($order->isCurrencyDifferent() ? "[{$ruleFeeAmount}] " : '');
            }
            $incl   = " {$baseRuleFeeAmountInclTax} "
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

    /**
     * @param MetadataProvider $subject
     * @param array $result
     *
     * @return mixed
     */
    public function afterGetHeaders(MetadataProvider $subject, $result)
    {
        $showExtraFee = $this->helperData->getConfigGeneral('enabled_order_grid');

        if (!$showExtraFee && ($key = array_search('Extra Fee', $result)) !== false) {
            unset($result[$key]);
        }

        return $result;
    }
}
