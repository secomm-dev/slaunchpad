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

define(
    [
        'jquery',
        'Magento_Checkout/js/model/resource-url-manager'
    ],
    function ($, resourceUrlManager) {
        "use strict";
        return $.extend(resourceUrlManager, {
            /**
             * Get update spend point url
             * @return {*|string}
             */
            getUrlForRuleInformation: function (quote) {
                var params = (this.getCheckoutMethod() === 'guest') ? {cartId: quote.getQuoteId()} : {};
                var urls = {
                    'customer': '/carts/mine/mpextrafee',
                    'guest': '/guest-carts/:cartId/mpextrafee'
                };
                return this.getUrl(urls, params);
            },
            getUrlForCollectTotal: function (quote) {
                var params = (this.getCheckoutMethod() === 'guest') ? {cartId: quote.getQuoteId()} : {};
                var urls = {
                    'customer': '/carts/mine/mpextrafee/collecttotal',
                    'guest': '/guest-carts/:cartId/mpextrafee/collecttotal'
                };
                return this.getUrl(urls, params);
            }
        });
    }
);

