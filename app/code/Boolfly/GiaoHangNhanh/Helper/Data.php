<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Boolfly\GiaoHangNhanh\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Checkout\Model\Session;

class Data extends AbstractHelper
{
    const CONVERSION_RATES = [
        'kgs' => 1,
        'lbs' => 0.453592,
    ];

    const GENERAL_PAYMENT_FOR_SHIPPING_COD = 'giaohangnhanh_setting/general/payment_for_shipping_cod';
    protected $checkoutSession;

    public function __construct(
        protected StoreManagerInterface                $storeManager,
        Context                                        $context,
        Session $checkoutSession
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Get the current weight unit from Magento configuration
     *
     * @return string
     */
    public function getCurrentWeightUnit(): string
    {
        return $this->scopeConfig->getValue(
            \Magento\Directory\Helper\Data::XML_PATH_WEIGHT_UNIT,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get the current weight unit from Magento configuration and convert the value to kilograms
     *
     * @param float $value The weight value to convert
     * @return mixed The converted weight value in kilograms
     */
    public function convertToKilograms($value): float
    {
        $value = (float)$value;
        // Get the weight unit from the configuration
        $weightUnit = $this->getCurrentWeightUnit();

        // Convert the value to kilograms based on the weight unit
        if (isset(self::CONVERSION_RATES[$weightUnit])) {
            return $value * self::CONVERSION_RATES[$weightUnit];
        }

        return $value;
    }

    /**
     * Get Payment type for calculate shipping COD.
     * @return mixed
     */
    public function getPaymentForShippingCod()
    {
        $value = $this->getConfig(self::GENERAL_PAYMENT_FOR_SHIPPING_COD) ?? "";

        return explode(',', $value);
    }

    /**
     * Get Config with path
     *
     * @param string $path
     * @return mixed
     */
    public function getConfig(string $path)
    {
        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Has the order with payment COD?
     * @param mixed $order
     * @throws \Exception
     * @return bool
     */
    public function isOrderPaymentCod($order = null)
    {
        try {
            $paymentMethodInConfig = $this->getPaymentForShippingCod() ?? [];
            if (is_object($order)) {
                $paymentMethod = $order->getPayment()->getMethod();
                return in_array($paymentMethod, $paymentMethodInConfig);
            }
    
            $paymentMethod = $this->checkoutSession->getQuote()->getPayment()->getMethod();
            return in_array($paymentMethod, $paymentMethodInConfig);
        } catch (\Exception $e) {
        }

        return false;
    }

    /**
     * @param mixed $order
     * @return float|int
     */
    public function getTotalOrder($order = null)
    {
        if (is_object($order)) {
            return round($order->getGrandTotal());
        }

        return round($this->checkoutSession->getQuote()->getSubtotal());
    }
}
