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

namespace Mageplaza\ExtraFee\Block\Multishipping\Checkout;

use Exception;
use Magento\Checkout\Model\SessionFactory as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Multishipping\Block\Checkout\Shipping;
use Magento\Multishipping\Model\Checkout\Type\Multishipping;
use Magento\Payment\Model\Config;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Api\Data\StoreInterface;
use Mageplaza\ExtraFee\Model\Multishipping\ExtraFee as MultishippingExtraFee;

/**
 * Class AbstractExtraFee
 * @package Mageplaza\ExtraFee\Block\Multishipping\Checkout
 */
class AbstractExtraFee extends Template
{
    /**
     * @var MultishippingExtraFee
     */
    protected $multiShippingExtraFee;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var Shipping
     */
    protected $shipping;

    /**
     * @var Config
     */
    protected $paymentConfig;

    /**
     * @var Multishipping
     */
    protected $multishipping;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * AbstractExtraFee constructor.
     *
     * @param Context $context
     * @param MultishippingExtraFee $multiShippingExtraFee
     * @param PriceCurrencyInterface $priceCurrency
     * @param Shipping $shipping
     * @param Config $paymentConfig
     * @param Multishipping $multishipping
     * @param CheckoutSession $checkoutSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        MultishippingExtraFee $multiShippingExtraFee,
        PriceCurrencyInterface $priceCurrency,
        Shipping $shipping,
        Config $paymentConfig,
        Multishipping $multishipping,
        CheckoutSession $checkoutSession,
        array $data = []
    ) {
        $this->multiShippingExtraFee = $multiShippingExtraFee;
        $this->priceCurrency         = $priceCurrency;
        $this->shipping              = $shipping;
        $this->paymentConfig         = $paymentConfig;
        $this->multishipping         = $multishipping;
        $this->checkoutSession       = $checkoutSession;

        parent::__construct($context, $data);
    }

    /**
     * @param Address $address
     * @param string $area
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function fetch($address, $area)
    {
        $this->multiShippingExtraFee->fetch($address, $area);
    }

    /**
     * @param Address $address
     * @param string $area
     *
     * @return array
     */
    public function getAllApplyRule($address, $area)
    {
        try {
            return $this->multiShippingExtraFee->getAllApplyRule($address, $area);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * @param Address $address
     * @param int $area
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getAppliedRule($address, $area)
    {
        return $this->multiShippingExtraFee->getApplyRule($address, $area);
    }

    /**
     * @return Address[]
     */
    public function getAddresses()
    {
        return $this->shipping->getAddresses();
    }

    /**
     * @return Address
     */
    public function getBillingAddress()
    {
        return $this->multishipping->getQuote()->getBillingAddress();
    }

    /**
     * @return bool
     */
    public function hasVirtualItems()
    {
        return $this->multishipping->getQuote()->hasVirtualItems();
    }

    /**
     * @return array
     */
    public function getActivePaymentMethods()
    {
        return $this->paymentConfig->getActiveMethods();
    }

    /**
     * @return StoreInterface
     * @throws NoSuchEntityException
     */
    public function getStore()
    {
        return $this->_storeManager->getStore();
    }

    /**
     * @return Quote
     */
    public function getQuote()
    {
        return $this->multishipping->getQuote();
    }

    /**
     * @return array
     */
    public function getExtraFeeNote()
    {
        $checkoutSession = $this->checkoutSession->create();
        return $checkoutSession->getExtraFeeMultiNote() ?: [];
    }
}
