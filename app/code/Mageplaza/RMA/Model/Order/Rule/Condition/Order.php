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
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Model\Order\Rule\Condition;

use Magento\Customer\Model\Config\Source\Group;
use Magento\Directory\Model\Config\Source\Allregion;
use Magento\Directory\Model\Config\Source\Country;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Payment\Model\Config\Source\Allmethods as AllPaymentMethods;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;
use Magento\Sales\Model\Config\Source\Order\Status;
use Magento\Shipping\Model\Config\Source\Allmethods;
use Magento\Store\Model\System\Store;

/**
 * Class Order
 * @method string getAttribute()
 * @method Order setAttributeOption($attributeOption)
 * @package Mageplaza\RMA\Model\Rule\Condition
 */
class Order extends AbstractCondition
{
    /**
     * @var Country
     */
    protected $_directoryCountry;

    /**
     * @var Allregion
     */
    protected $_directoryAllRegion;

    /**
     * @var Allmethods
     */
    protected $_shippingAllMethods;

    /**
     * @var AllPaymentMethods
     */
    protected $_paymentAllMethods;

    /**
     * @var Status
     */
    protected $_orderStatus;

    /**
     * @var Store
     */
    protected $_store;

    /**
     * @var Group
     */
    protected $_customerGroup;

    /**
     * @var RequestInterface
     */
    protected $_request;

    /**
     * Order constructor.
     *
     * @param Context $context
     * @param Country $directoryCountry
     * @param Allregion $directoryAllRegion
     * @param Allmethods $shippingAllMethods
     * @param AllPaymentMethods $paymentAllMethods
     * @param Status $orderStatus
     * @param Store $store
     * @param Group $customerGroup
     * @param RequestInterface $request
     * @param array $data
     */
    public function __construct(
        Context $context,
        Country $directoryCountry,
        Allregion $directoryAllRegion,
        Allmethods $shippingAllMethods,
        AllPaymentMethods $paymentAllMethods,
        Status $orderStatus,
        Store $store,
        Group $customerGroup,
        RequestInterface $request,
        array $data = []
    ) {
        $this->_directoryCountry = $directoryCountry;
        $this->_directoryAllRegion = $directoryAllRegion;
        $this->_shippingAllMethods = $shippingAllMethods;
        $this->_paymentAllMethods = $paymentAllMethods;
        $this->_orderStatus = $orderStatus;
        $this->_store = $store;
        $this->_customerGroup = $customerGroup;
        $this->_request = $request;

        parent::__construct($context, $data);
    }

    /**
     * Load attribute options
     *
     * @return $this
     */
    public function loadAttributeOptions()
    {
        $attributes = [
            'base_subtotal' => __('Subtotal (Base)'),
            'base_grand_total' => __('Grand Total (Base)'),
            'base_total_refunded' => __('Total Refunded (Base)'),
            'base_total_invoiced' => __('Total Invoiced (Base)'),
            'total_qty_ordered' => __('Total Items Quantity'),
            'weight' => __('Total Weight'),
            'status' => __('Status'),
            'store_id' => __('Purchase Point'),
            'customer_group_id' => __('Customer Group'),
            'payment_method' => __('Payment Method'),
            'shipping_method' => __('Shipping Method'),
            'shipping_postcode' => __('Shipping Postcode'),
            'shipping_region' => __('Shipping Region'),
            'shipping_region_id' => __('Shipping State/Province'),
            'shipping_country_id' => __('Shipping Country'),
        ];
        if ($this->_request->getFullActionName() === 'mprma_shippingLabel_edit') {
            $attributes = [
                'shipping_postcode' => __('Shipping Postcode'),
                'shipping_region' => __('Shipping Region'),
                'shipping_region_id' => __('Shipping State/Province'),
                'shipping_country_id' => __('Shipping Country'),
            ];
        }

        $this->setAttributeOption($attributes);

        return $this;
    }

    /**
     * Get attribute element
     *
     * @return $this|AbstractCondition
     */
    public function getAttributeElement()
    {
        $element = parent::getAttributeElement();
        $element->setShowAsText(true);

        return $element;
    }

    /**
     * Get input type
     *
     * @return string
     */
    public function getInputType()
    {
        switch ($this->getAttribute()) {
            case 'base_subtotal':
            case 'base_grand_total':
            case 'base_total_refunded':
            case 'base_total_invoiced':
            case 'weight':
            case 'total_qty_ordered':
                return 'numeric';

            case 'status':
            case 'store_id':
            case 'customer_group_id':
            case 'shipping_method':
            case 'payment_method':
            case 'shipping_country_id':
            case 'shipping_region_id':
                return 'multiselect';
        }

        return 'string';
    }

    /**
     * @return array
     */
    public function getDefaultOperatorInputByType()
    {
        $operator = parent::getDefaultOperatorInputByType();
        $operator['multiselect'] = ['()', '!()'];

        return $operator;
    }

    /**
     * Get value element type
     *
     * @return string
     */
    public function getValueElementType()
    {
        switch ($this->getAttribute()) {
            case 'status':
            case 'store':
            case 'customer_group_id':
            case 'shipping_method':
            case 'payment_method':
            case 'shipping_country_id':
            case 'shipping_region_id':
                return 'multiselect';
        }

        return 'text';
    }

    /**
     * Get value select options
     *
     * @return array|mixed
     */
    public function getValueSelectOptions()
    {
        if (!$this->hasData('value_select_options')) {
            switch ($this->getAttribute()) {
                case 'shipping_country_id':
                    $options = $this->_directoryCountry->toOptionArray();
                    break;
                case 'shipping_region_id':
                    $options = $this->_directoryAllRegion->toOptionArray();
                    break;
                case 'shipping_method':
                    $options = $this->_shippingAllMethods->toOptionArray();
                    break;
                case 'payment_method':
                    $options = $this->_paymentAllMethods->toOptionArray();
                    break;
                case 'status':
                    $options = $this->_orderStatus->toOptionArray();
                    break;
                case 'store':
                    $options = $this->_store->toOptionArray();
                    break;
                case 'customer_group_id':
                    $options = $this->_customerGroup->toOptionArray();
                    $options[0]['label'] = 'NOT LOGGED IN';
                    $options[0]['value'] = 0;
                    break;
                default:
                    $options = [];
            }
            $this->setData('value_select_options', $options);
        }

        return $this->getData('value_select_options');
    }

    /**
     * Validate Order Rule Condition
     *
     * @param AbstractModel $order
     *
     * @return bool
     */
    public function validate(AbstractModel $order)
    {
        /** @var \Magento\Sales\Model\Order $order */
        switch ($this->getAttribute()) {
            case 'payment_method':
                $orderPayment = $order->getPayment() ? $order->getPayment()->getMethod() : '';
                $order->setPaymentMethod($orderPayment);
                break;

            case 'shipping_postcode':
                if ($order->getShippingAddress()) {
                    $order->setShippingPostcode($order->getShippingAddress()->getPostcode());
                }
                break;

            case 'shipping_region':
                if ($order->getShippingAddress()) {
                    $order->setShippingRegion($order->getShippingAddress()->getRegion());
                }
                break;

            case 'shipping_region_id':
                if ($order->getShippingAddress()) {
                    $order->setShippingRegionId($order->getShippingAddress()->getRegionId());
                }
                break;

            case 'shipping_country_id':
                if ($order->getShippingAddress()) {
                    $order->setShippingCountryId($order->getShippingAddress()->getCountryId());
                }
                break;
        }

        return parent::validate($order);
    }
}
