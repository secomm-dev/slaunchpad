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

namespace Mageplaza\ExtraFee\Api;

use Magento\Checkout\Api\Data\ShippingInformationInterface;

/**
 * Interface RuleInterface
 * @package Mageplaza\ExtraFee\Api
 */
interface RuleInterface
{
    /**
     * @param int $cartId
     * @param string $area
     * @param ShippingInformationInterface $addressInformation
     *
     * @return string
     */
    public function update(
        $cartId,
        $area,
        ShippingInformationInterface $addressInformation
    );

    /**
     * @param int $cartId
     * @param string $area
     * @param ShippingInformationInterface $addressInformation
     *
     * @return \Mageplaza\ExtraFee\Api\Data\ListInterface
     */
    public function getRules(
        $cartId,
        $area,
        ShippingInformationInterface $addressInformation
    );

    /**
     * @param int $cartId
     * @param string $formData
     * @param string $area
     *
     * @return \Magento\Quote\Api\Data\TotalsInterface Quote totals data.
     */
    public function collectTotal(
        $cartId,
        $formData,
        $area
    );

    /**
     * @param int $cartId
     * @param string $area
     * @param string $shippingMethods
     *
     * @return string
     */
    public function updateMultiShipping(
        $cartId,
        $area,
        $shippingMethods
    );
}
