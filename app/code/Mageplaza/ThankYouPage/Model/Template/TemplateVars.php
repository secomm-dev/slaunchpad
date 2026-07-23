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
 * @package     Mageplaza_ThankYouPage
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ThankYouPage\Model\Template;

/**
 * Class TemplateVars
 * @package Mageplaza\ThankYouPage\Model\Template
 */
class TemplateVars
{
    /**
     * @var array
     */
    protected $customerAttr = [];

    /**
     * @var array
     */
    protected $orderAttr = [];

    /**
     * @var array
     */
    protected $couponAttr = [];

    /**
     * @var array
     */
    protected $storeAtt = [];

    /**
     * @var array
     */
    protected $otherAttr = [];

    /**
     * @return array
     */
    public function getTemplateVars()
    {
        $varLabels = [
            'customer' => ['label' => __('Customer Attributes'), 'values' => $this->getCustomerVars()],
            'order'    => ['label' => __('Order Attributes'), 'values' => $this->getOrderVars()],
            'coupon'   => ['label' => __('Coupon Attributes'), 'values' => $this->getCouponAttr()],
            'store'    => ['label' => __('Store Attributes'), 'values' => $this->getStoreAttr()],
            'other'    => ['label' => __('Other Attributes'), 'values' => $this->getOtherAttr()]
        ];

        return $varLabels;
    }

    /**
     * @return array
     */
    public function getCustomerVars()
    {
        $this->customerAttr = [
            ['value' => 'customer.entity_id', 'label' => __('Customer Id')],
            ['value' => 'customer.email', 'label' => __('Customer Email')],
            ['value' => 'customer.group_id', 'label' => __('Customer Group')],
            ['value' => 'customer.firstname', 'label' => __('Customer First Name')],
            ['value' => 'customer.lastname', 'label' => __('Customer Last Name')],
            ['value' => 'customer.name', 'label' => __('Customer Name')],
            ['value' => 'customer.gender', 'label' => __('Customer Gender')],
        ];

        return $this->customerAttr;
    }

    /**
     * @return array
     */
    public function getOrderVars()
    {
        $this->orderAttr = [
            ['value' => 'order.increment_id', 'label' => __('Order Id')],
            ['value' => 'order.coupon_code', 'label' => __('Order Coupon')],
            ['value' => 'order.shipping_description', 'label' => __('Shipping Description')],
            ['value' => 'order.is_virtual', 'label' => __('Is Virtual')],
            ['value' => 'order.store_id', 'label' => __('Store Id')],
            ['value' => 'order.grand_total', 'label' => __('Grand Total')],
            ['value' => 'order.subtotal', 'label' => __('Subtotal')],
            ['value' => 'order.shipping_amount', 'label' => __('Shipping Amount')],
            ['value' => 'order.tax_amount', 'label' => __('Tax Amount')],
            ['value' => 'order.total_qty_ordered', 'label' => __('Total Qty Ordered')],
            ['value' => 'order.order_currency_code', 'label' => __('Order Currency Code')],
            ['value' => 'order.shipping_method', 'label' => __('Shipping Method')],
            ['value' => 'order.created_at', 'label' => __('Created at')]
        ];

        return $this->orderAttr;
    }

    /**
     * @return array
     */
    public function getCouponAttr()
    {
        $this->couponAttr = [
            ['value' => 'coupon.rule_id', 'label' => __('Rule Id')],
            ['value' => 'coupon.description', 'label' => __('Rule Description')],
            ['value' => 'coupon.code', 'label' => __('Coupon Code')],
            ['value' => 'coupon.discount_amount', 'label' => __('Discount Amount')]
        ];

        return $this->couponAttr;
    }

    /**
     * @return array
     */
    public function getStoreAttr()
    {
        $this->storeAtt = [
            ['value' => 'store.name', 'label' => __('Store Name')],
            ['value' => 'store.store_id', 'label' => __('Store Id')],
            ['value' => 'store.base_url', 'label' => __('Store Base Url')],
            ['value' => 'store.current_url', 'label' => __('Current Url')],
            ['value' => 'store.base_currency_code', 'label' => __('Store Base Currency Code')],
            ['value' => 'store.current_currency_code', 'label' => __('Store Current Currency Code')]
        ];

        return $this->storeAtt;
    }

    /**
     * @return array
     */
    public function getOtherAttr()
    {
        $this->otherAttr = [
            ['value' => 'items', 'label' => __('Order Items')],
            ['value' => 'total', 'label' => __('Order Total')],
            ['value' => 'payment_html', 'label' => __('Payment Method')],
            ['value' => 'formattedShippingAddress', 'label' => __('Shipping Address')],
            ['value' => 'formattedBillingAddress', 'label' => __('Billing Address')],
            ['value' => 'static_block_1', 'label' => __('Static Block 1')],
            ['value' => 'static_block_2', 'label' => __('Static Block 2')]
        ];

        return $this->otherAttr;
    }
}
