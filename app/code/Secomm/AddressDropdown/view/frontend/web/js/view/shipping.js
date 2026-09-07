define([
    'jquery',
    'Magento_Checkout/js/view/shipping'
], function ($, Component) {
    'use strict';

    return Component.extend({
        initialize: function () {
            this._super();
            this.initObservable();
            return this;
        },

        setShippingInformation: function () {
            if (this.validateShippingInformation()) {
                this._super();
            }
        }
    });
});
