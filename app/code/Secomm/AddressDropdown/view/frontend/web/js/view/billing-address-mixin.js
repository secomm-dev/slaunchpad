/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
        'jquery',
        'ko',
        'underscore',
        'Magento_Ui/js/form/form',
        'Magento_Customer/js/model/customer',
        'Magento_Customer/js/model/address-list',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/create-billing-address',
        'Magento_Checkout/js/action/select-billing-address',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/checkout-data-resolver',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/action/set-billing-address',
        'Magento_Ui/js/model/messageList',
    ],
    function (
        $,
        ko,
        _,
        Component,
        customer,
        addressList,
        quote,
        createBillingAddress,
        selectBillingAddress,
        checkoutData,
        checkoutDataResolver,
        customerData,
        setBillingAddressAction,
        globalMessageList,
    ) {
    'use strict';

    var lastSelectedBillingAddress = null,
        addressUpdated = false;
    let cityData = customerData.get('city-data');


    var mixin = {
        defaults: {
            detailsTemplate: 'Secomm_AddressDropdown/billing-address/details',
        },
        CUSTOM_SUB_CITY_SELECTOR: '#co-payment-form #custom-sub-city-select-billing',
        BILLING_ADDRESS_SUB_CITY: '.billing-address-sub-city',
        /**
         * @return {Boolean}
         */
        useShippingAddress: function () {
            if (this.isAddressSameAsShipping()) {
                selectBillingAddress(quote.shippingAddress());
                this.updateAddresses(true);
                this.isAddressDetailsVisible(true);
            } else {
                lastSelectedBillingAddress = quote.billingAddress();
                quote.billingAddress(null);
                this.isAddressDetailsVisible(false);
            }
            checkoutData.setSelectedBillingAddress(null);

            return true;
        },

        /**
         * Update address action
         */
        updateAddress: function () {
            var addressData, newBillingAddress;

            addressUpdated = true;

            if (this.selectedAddress() && !this.isAddressFormVisible()) {
                selectBillingAddress(this.selectedAddress());
                checkoutData.setSelectedBillingAddress(this.selectedAddress().getKey());
            } else {
                this.source.set('params.invalid', false);
                this.source.trigger(this.dataScopePrefix + '.data.validate');

                if (this.source.get(this.dataScopePrefix + '.custom_attributes')) {
                    this.source.trigger(this.dataScopePrefix + '.custom_attributes.data.validate');
                }

                const subCityValidate = $(this.CUSTOM_SUB_CITY_SELECTOR).val() === '' && $(this.BILLING_ADDRESS_SUB_CITY).is(':visible')

                if (!this.source.get('params.invalid') && !subCityValidate) {
                    addressData = this.source.get(this.dataScopePrefix);
                    addressData.custom_attributes = this.source.get('billingAddress').custom_attributes;

                    if (customer.isLoggedIn() && !this.customerHasAddresses) { //eslint-disable-line max-depth
                        this.saveInAddressBook(1);
                    }
                    addressData['save_in_address_book'] = this.saveInAddressBook() ? 1 : 0;
                    newBillingAddress = createBillingAddress(addressData);
                    // New address must be selected as a billing address
                    selectBillingAddress(newBillingAddress);
                    checkoutData.setSelectedBillingAddress(newBillingAddress.getKey());
                    checkoutData.setNewCustomerBillingAddress(addressData);
                }
            }
            this.updateAddresses(true);
        },

        /**
         * Trigger action to update shipping and billing addresses
         *
         * @param {Boolean} force
         */
        updateAddresses: function (force) {
            force = !(typeof force === 'undefined' || force !== true);

            if (force
                || window.checkoutConfig.reloadOnBillingAddress
                || !window.checkoutConfig.displayBillingOnPaymentMethod) {
                setBillingAddressAction(globalMessageList);
            }
        },

        canUseShippingAddress: ko.computed(function () {
            const status = !quote.isVirtual() && quote.shippingAddress() && quote.shippingAddress().canUseForBilling();
            const subCity = $("#custom-sub-city-select").val();
            if (status && subCity) {
                let shippingAddress = quote.shippingAddress();
                var extension = {
                    sub_city: subCity,
                };
                shippingAddress.extension_attributes = $.extend(
                    shippingAddress.extension_attributes,
                    extension
                );
            }
            return status;
        }),

        /**
         * @param {String} cityId
         * @return {String}
         */
        getCityName: function (address,cityId) {
            try {
                return cityData()[address.regionId].city[cityId]['name'] !== undefined ? cityData()[address.regionId].city[cityId]['name'] : cityId;
            }catch (e) {
                return cityId;
            }
        },

        /**
         * @param {String} subCityId
         * @return {String}
         */
        getSubCityName: function (parent, subCityId) {
            try {
                let address = parent.currentBillingAddress();
                let cityId = address.city;
                let regionId = address.regionId;
                return cityData()[regionId].city[cityId].sub_city[subCityId].name ?? subCityId;
            } catch (e) {
                return subCityId;
            }
        },
    };

    return function (target) {
        return target.extend(mixin);
    };
});
