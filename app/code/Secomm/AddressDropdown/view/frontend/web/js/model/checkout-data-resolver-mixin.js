define([
    'mage/utils/wrapper',
    'jquery',
    'Magento_Customer/js/model/address-list',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/action/select-billing-address',
], function (wrapper,
             $,
             addressList,
             quote,
             checkoutData,
             selectBillingAddress,) {
    'use strict';

    return function (target) {
        target.applyBillingAddress = wrapper.wrapSuper(target.applyBillingAddress, function () {
            var shippingAddress,
                isBillingAddressInitialized;

            if (quote.billingAddress()) {
                selectBillingAddress(quote.billingAddress());

                return;
            }

            if (quote.isVirtual() || !quote.billingAddress()) {
                isBillingAddressInitialized = addressList.some(function (addrs) {
                    if (addrs.isDefaultBilling()) {
                        const subCity = addrs?.customAttributes?.find(item => item.attribute_code === "sub_city")?.value;
                        var extension = {
                            sub_city: subCity,
                        };
                        addrs.extension_attributes = $.extend(
                            addrs.extension_attributes,
                            extension
                        );
                        selectBillingAddress(addrs);

                        return true;
                    }

                    return false;
                });
            }

            shippingAddress = quote.shippingAddress();

            if (!isBillingAddressInitialized &&
                shippingAddress &&
                shippingAddress.canUseForBilling() &&
                (shippingAddress.isDefaultShipping() || !quote.isVirtual())
            ) {
                //set billing address same as shipping by default if it is not empty
                selectBillingAddress(quote.shippingAddress());
            }
        });

        return target;
    };
});
