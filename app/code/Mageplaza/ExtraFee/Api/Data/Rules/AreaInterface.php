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

namespace Mageplaza\ExtraFee\Api\Data\Rules;

/**
 * Interface AreaInterface
 * @package Mageplaza\ExtraFee\Api\Data\Rules
 */
interface AreaInterface
{
    const PAYMENT_METHOD  = 'payment_method';
    const SHIPPING_METHOD = 'shipping_method';
    const CART_SUMMARY    = 'cart_summary';

    /**
     * @return \Mageplaza\ExtraFee\Api\Data\RulesDataInterface[]
     */
    public function getPaymentMethod();

    /**
     * @param \Mageplaza\ExtraFee\Api\Data\RulesDataInterface[] $value
     *
     * @return $this
     */
    public function setPaymentMethod($value);

    /**
     * @return \Mageplaza\ExtraFee\Api\Data\RulesDataInterface[]
     */
    public function getShippingMethod();

    /**
     * @param \Mageplaza\ExtraFee\Api\Data\RulesDataInterface[] $value
     *
     * @return $this
     */
    public function setShippingMethod($value);

    /**
     * @return \Mageplaza\ExtraFee\Api\Data\RulesDataInterface[]
     */
    public function getCartSummary();

    /**
     * @param \Mageplaza\ExtraFee\Api\Data\RulesDataInterface[] $value
     *
     * @return $this
     */
    public function setCartSummary($value);
}
