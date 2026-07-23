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

namespace Mageplaza\ThankYouPage\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\View\Asset\Repository;

/**
 * Class OrderPageStyle
 * @package Mageplaza\ThankYouPage\Model\Config\Source
 */
class OrderPageStyle implements OptionSourceInterface
{
    const SIMPLE  = 'simple';
    const COMPLEX = 'complex';

    /**
     * @var Repository
     */
    private $_assetRepo;

    /**
     * OrderPageStyle constructor.
     *
     * @param Repository $assetRepo
     */
    public function __construct(Repository $assetRepo)
    {
        $this->_assetRepo = $assetRepo;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => self::SIMPLE,
                'label' => __('Simple Style')
            ],
            [
                'value' => self::COMPLEX,
                'label' => __('Complex Style')
            ]
        ];

        return $options;
    }

    /**
     * @return array
     */
    public function getImageUrls()
    {
        $urls = [];
        foreach ($this->toOptionArray() as $template) {
            $urls[$template['value']] = $this->_assetRepo->getUrl('Mageplaza_ThankYouPage::images/' . $template['value'] . '.jpg');
        }

        return $urls;
    }

    /**
     * @return array
     */
    public function getOrderDetailHtml()
    {
        $label = [
            'order_details'   => __('Order Details'),
            'order_number'    => __('Order Number:'),
            'total'           => __('Total:'),
            'order_create_at' => __('Order create at:'),
            'billing_address' => __('Billing Address'),
            'shipping_method' => __('Shipping Method'),
            'payment_method'  => __('Payment Method'),
            'delivery_time'   => __('Delivery Time'),
        ];

        $simpleHTML = <<<HTML
<div class="mp-order-detail">
    <div class="order-details col-mp">
        <h3 class="mp-block-heading">{$label['order_details']}</h3>
        <p>{$label['order_number']} <b>#{{ order.increment_id }}</b></p>
        <p>{$label['total']} <b>{{ total }}</b></p>
        <p>{$label['order_create_at']} <b>{{ created_at_formatted }}</b></p>
    </div>
    <div class="billing-address col-mp">
        <h3 class="mp-block-heading">{$label['billing_address']}</h3>
        <p>{{formattedBillingAddress}}</p>
    </div>
    {% if order.is_virtual == 0 %}
    <div class="shipping-method col-mp">
        <h3 class="mp-block-heading">{$label['shipping_method']}</h3>
        <p>{{ order.shipping_description }}</p>
        {% if order.mp_delivery_information %}
        <h3 class="mp-block-heading">{$label['delivery_time']}</h3>
        <p>{{ order.mp_delivery_information }}</p>
        {% endif %}
    </div>
    {% endif %}
    <div class="payment-method col-mp">
        <h3 class="mp-block-heading">{$label['payment_method']}</h3>
        <table class="paymentTable">
            <tbody>
                <tr>
                    <td>{{ payment_html }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
HTML;

        $options = [
            self::SIMPLE  => $simpleHTML,
            self::COMPLEX => $simpleHTML
        ];

        return $options;
    }
}
