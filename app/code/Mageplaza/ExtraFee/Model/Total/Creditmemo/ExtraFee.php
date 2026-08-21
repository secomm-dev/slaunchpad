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

namespace Mageplaza\ExtraFee\Model\Total\Creditmemo;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;
use Mageplaza\ExtraFee\Helper\Data;

/**
 * Class ExtraFee
 * @package Mageplaza\ExtraFee\Model\Total\Creditmemo
 */
class ExtraFee extends AbstractTotal
{
    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * ExtraFee constructor.
     *
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        PriceCurrencyInterface $priceCurrency,
        Data $helper,
        RequestInterface $request,
        array $data = []
    ) {
        $this->helper        = $helper;
        $this->priceCurrency = $priceCurrency;
        $this->request       = $request;

        parent::__construct($data);
    }

    /**
     * Collect Creditmemo subtotal
     *
     * @param Creditmemo $creditmemo
     *
     * @return $this
     * @throws LocalizedException
     */
    public function collect(Creditmemo $creditmemo)
    {
        $order          = $creditmemo->getOrder();
        $extraFeeTotals = $this->helper->getObjectExtraFeeTotals($creditmemo, $order);

        foreach ($extraFeeTotals as $fee) {
            $maxFee = round($fee['value_incl_tax'], 2);
            if ($fee['rf']) {
                if ($creditmemo->getData($fee['code']) && $this->request->getControllerName() === 'order_creditmemo'
                    && $this->request->getActionName() !== 'updateQty') {
                    $desiredAmount = $this->priceCurrency->round($creditmemo->getData($fee['code']));
                    if ($desiredAmount <= $maxFee) {
                        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $creditmemo[$fee['code']]);
                        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $creditmemo[$fee['code']]);
                    } else {
                        throw new \Magento\Framework\Exception\LocalizedException(
                            __('Maximum %1 amount allowed to refund is: %2', $fee['label'], $maxFee)
                        );
                    }
                } else {
                    $feeValue     = $fee['apply_type'] == 2 ? round($fee['value_incl_tax'], 2) : round($fee['value'], 2);
                    $baseFeeValue = $fee['apply_type'] == 2 ? round($fee['base_value_incl_tax'], 2) : round($fee['base_value'], 2);
                    $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $feeValue);
                    $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseFeeValue);
                }
            }
        }

        return $this;
    }
}
