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
 * @package     Mageplaza_OscPro
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\OscPro\Helper;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Newsletter\Model\Subscriber;
use Magento\ReCaptchaUi\Model\UiConfigResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\Osc\Helper\Data as AbstractData;

/**
 * Class Data
 * @package Mageplaza\OscPro\Helper
 */
class Data extends AbstractData
{
    const CONFIG_PATH_LOADING_SPEED = 'loading_speed_optimization';
    /**
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param StoreManagerInterface $storeManager
     * @param EncryptorInterface $encryptor
     * @param Json $json
     * @param Subscriber $subscriber
     * @param Session $checkoutSession
     * @param UiConfigResolverInterface $captchaUiConfigResolver
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encryptor,
        Json $json,
        Subscriber $subscriber,
        Session $checkoutSession,
        UiConfigResolverInterface $captchaUiConfigResolver
    ) {
        parent::__construct($context, $objectManager, $storeManager, $encryptor, $json, $subscriber, $checkoutSession, $captchaUiConfigResolver);
    }

    /********************************** Loading Speed Optimization *********************
     *
     * @param $code
     * @param null $store
     *
     * @return mixed
     */
    public function getLoadingSpeedConfig($code = '', $store = null)
    {
        $code = $code ? self::CONFIG_PATH_LOADING_SPEED . '/' . $code : self::CONFIG_PATH_LOADING_SPEED;

        $result = $this->getModuleConfig($code, $store);

        if ($code === 'loading_speed_optimization') {
            $result['apply_coupon']            = $result['apply_coupon'] ?: '0';
            $result['billing_address_change']  = $result['billing_address_change'] ?: '0';
            $result['gift_wrap']               = $result['gift_wrap'] ?: '0';
            $result['payment_method_change']   = $result['payment_method_change'] ?: '0';
            $result['product_change']          = $result['product_change'] ?: '0';
            $result['shipping_address_change'] = $result['shipping_address_change'] ?: '0';
            $result['shipping_method_change']  = $result['shipping_method_change'] ?: '0';
        }

        return $result;
    }
    /**
     * @param null $store
     *
     * @return bool
     */
    public function isCustomRefreshPage($store = null)
    {
        return !$this->getLoadingSpeedConfig('refresh_page', $store);
    }

    /**
     * @param $store
     *
     * @return mixed
     */
    public function getShippingAddressChangeConfig($store = null)
    {
        return $this->getLoadingSpeedConfig('shipping_address_change', $store);
    }

    /**
     * @param $store
     *
     * @return mixed
     */
    public function getBillingAddressChangeConfig($store = null)
    {
        return $this->getLoadingSpeedConfig('billing_address_change', $store);
    }

    /**
     * @param $store
     *
     * @return mixed
     */
    public function getShippingMethodChangeConfig($store = null)
    {
        return $this->getLoadingSpeedConfig('shipping_method_change', $store);
    }

    /**
     * @param $store
     *
     * @return mixed
     */
    public function getPaymentMethodChangeConfig($store = null)
    {
        return $this->getLoadingSpeedConfig('payment_method_change', $store);
    }

    /**
     * @param $store
     *
     * @return mixed
     */
    public function getQtyProductChangeConfig($store = null)
    {
        return $this->getLoadingSpeedConfig('product_change', $store);
    }

    /**
     * @param $store
     *
     * @return mixed
     */
    public function getApplyCouponConfig($store = null)
    {
        return $this->getLoadingSpeedConfig('apply_coupon', $store);
    }

    /**
     * @param $store
     *
     * @return mixed
     */
    public function getGiftWrapConfig($store = null)
    {
        return $this->getLoadingSpeedConfig('gift_wrap', $store);
    }
}
