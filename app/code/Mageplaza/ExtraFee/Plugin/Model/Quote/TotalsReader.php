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

namespace Mageplaza\ExtraFee\Plugin\Model\Quote;

use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class TotalsReader
 * @package Mageplaza\ExtraFee\Plugin\Model\Quote
 */
class TotalsReader
{
    /**
     * @var Data
     */
    public $helperData;

    /**
     * TotalsReader constructor.
     *
     * @param Data $helperData
     */
    public function __construct(
        Data $helperData
    ) {
        $this->helperData = $helperData;
    }

    /**
     * add fee tax amount to tax info
     *
     * @param \Magento\Quote\Model\Quote\TotalsReader $subject
     * @param $result
     * @param \Magento\Quote\Model\Quote $quote
     * @param array $total
     *
     * @return mixed
     */
    public function afterFetch(
        \Magento\Quote\Model\Quote\TotalsReader $subject,
        $result,
        \Magento\Quote\Model\Quote $quote,
        array $total
    ) {
        $extraFee = $this->helperData->getMpExtraFee($quote, 4);
        if (isset($extraFee[0])) {
            $extraFee = $extraFee[0];
            if ($extraFee['apply_type'] == 2) {
                $result['tax']->setValue($result['tax']->getValue() + $extraFee['value_incl_tax'] - $extraFee['value_excl_tax']);
            }

            $data = $result['tax']->getFullInfo();

            $data[$extraFee['code']] = [
                'amount'      => ($extraFee['value_excl_tax'] / 100 * $extraFee['percent']),
                'base_amount' => ($extraFee['value_excl_tax'] / 100 * $extraFee['percent']),
                'percent'     => $extraFee['percent'],
                'id'          => $extraFee['code'],
                'rates'       => [
                    [
                        'percent' => $extraFee['percent'],
                        'code'    => $extraFee['code'],
                        'title'   => $extraFee['rule_label'] . ' - ' . $extraFee['title'],
                    ]
                ]
            ];

            $result['tax']->setFullInfo($data);
        }

        return $result;
    }

}
