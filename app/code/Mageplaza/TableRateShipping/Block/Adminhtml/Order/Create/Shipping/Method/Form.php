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
 * @package     Mageplaza_TableRateShipping
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */
namespace Mageplaza\TableRateShipping\Block\Adminhtml\Order\Create\Shipping\Method;

use Magento\Csp\Helper\CspNonceProvider;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Sales\Block\Adminhtml\Order\Create\Shipping\Method\Form as MagentoForm;

/**
 * Class Form
 * Mageplaza\TableRateShipping\Block\Adminhtml\Order\Create\Shipping\Method
 */
class Form extends Template
{
    /**
     * @var SecureHtmlRenderer
     */
    protected $secureRenderer;

    /**
     * @var MagentoForm
     */
    protected $magentoForm;

    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * @var CspNonceProvider
     */
    protected $cspNonceProvider;


    /**
     * Form constructor.
     *
     * @param MagentoForm $magentoForm
     * @param Escaper $escaper
     * @param CspNonceProvider $cspNonceProvider
     * @param SecureHtmlRenderer $secureRenderer
     */
    public function __construct(
        MagentoForm $magentoForm,
        Escaper $escaper,
        CspNonceProvider $cspNonceProvider,
        SecureHtmlRenderer $secureRenderer
    ) {
        $this->magentoForm      = $magentoForm;
        $this->escaper          = $escaper;
        $this->cspNonceProvider = $cspNonceProvider;
        $this->secureRenderer   = $secureRenderer;
    }

    /**
     * @return string
     */
    public function renderScriptForm()
    {
        $html         = '';
        $script       = '';
        $isVirtual    = $this->magentoForm->getQuote()->isVirtual() ? 'true' : 'false';
        $isNotVirtual = !$this->magentoForm->getQuote()->isVirtual() ? 'true' : 'false';
        $script       .=
            <<<script
            require(["Magento_Sales/order/create/form"], function(){
                    order.overlay('shipping-method-overlay', {$isNotVirtual});
                    order.overlay('address-shipping-overlay', {$isNotVirtual});
                    order.isOnlyVirtualProduct = {$isVirtual};

                    (function (parent) {
                        AdminOrder.prototype.accountGroupChange = function (method) {
                            var params = this.serializeData('order-form_account').toObject();
                            params.collect_shipping_rates = 1;
                            this.loadArea(['data'], true, params);
                        };
                    }(AdminOrder.prototype.accountGroupChange));
                });
            script;

        $html .= $this->secureRenderer->renderTag('script', ['type' => 'text/javascript'], $script, false);

        return $html;
    }

    /**
     * @return string
     */
    public function renderShowShippingMethodScript()
    {
        $html   = '';
        $script = <<<script
            require(['prototype'], function() {
                $('order-shipping-method-choose').show();
            });
        script;

        $html .= $this->secureRenderer->renderTag('script', ['type' => 'text/javascript'], $script, false);

        return $html;
    }

    /**
     * @param $code
     *
     * @return array
     */
    public function getRadioProperty($code)
    {
        return [
            'name'                 => 'order[shipping_method]',
            'type'                 => 'radio',
            'data-shipping-method' => $code,
            'onclick'              => 'order.setShippingMethod(this.value)',
        ];
    }

    /**
     * @return string
     */
    public function renderOnClickShippingMethodSummary()
    {
        $html   = '';
        $script = <<<script
            require(['jquery'], function($) {
                $("#on-click-order-shipping-method-summary").on("click", function () {
                    order.loadShippingRates();return false
                });
            });
        script;

        $html .= $this->secureRenderer->renderTag('script', ['type' => 'text/javascript'], $script, false);

        return $html;
    }

    /**
     * @return string
     */
    public function renderToggleScript()
    {
        $html   = '';
        $script = <<<script
            require(['jquery'], function($) {
                $(document).on('click', '[data-toggle-shipping-method]', function(e) {
                    e.preventDefault();
                    $('#order-shipping-method-info').hide();
                    $('#order-shipping-method-choose').show();
                });
            });
        script;

        $html .= $this->secureRenderer->renderTag('script', ['type' => 'text/javascript'], $script, false);

        return $html;
    }

    /**
     * @param $shippingRateGroups
     *
     * @return string
     */
    public function renderOnclickForShippingRate($shippingRateGroups)
    {
        $html = '';
        $codes = [];

        $script = <<<script
            require(['jquery'], function($) {

        script;

        foreach ($shippingRateGroups as $rates) {
            foreach ($rates as $rate) {
                $codes[] = $rate->getCode();
            }

        }

        $codes = array_unique($codes);

        foreach ($codes as $code) {
            $script .= <<<script
                $("#s_method_$code").on("click", function () {
                    order.setShippingMethod(this.value)
                });
        script;
        }

        $script .= <<<script
            });
        script;
        $html .= $this->secureRenderer->renderTag('script', ['type' => 'text/javascript'], $script, false);

        return $html;
    }
}
