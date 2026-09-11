<?php

namespace Secomm\VNPAY\Model;

/**
 * Class Vnpay
 *
 * @method \Magento\Quote\Api\Data\PaymentMethodExtensionInterface getExtensionAttributes()
 */
class Vnpay extends \Magento\Payment\Model\Method\AbstractMethod
{
    public const PAYMENT_METHOD_VNPAY_CODE = 'vnpay';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_VNPAY_CODE;

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;


}
