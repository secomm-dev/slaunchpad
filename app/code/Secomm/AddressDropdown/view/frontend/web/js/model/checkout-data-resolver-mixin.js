define([
    'mage/utils/wrapper',
    'jquery',
    'Magento_Customer/js/model/address-list',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/action/select-billing-address',
    'Secomm_AddressDropdown/js/model/master-switch',
], function (wrapper,
             $,
             addressList,
             quote,
             checkoutData,
             selectBillingAddress,
             isEnabled,) {
    'use strict';

    return function (target) {
        if (!isEnabled()) {
            // TASK-SEC-A5: module disabled for this store scope — keep native behavior intact.
            return target;
        }
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
