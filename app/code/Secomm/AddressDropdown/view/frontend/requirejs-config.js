/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

var config = {
    map: {
        '*': {
            directoryAddressDropdownUpdater: 'Secomm_AddressDropdown/js/address-dropdown',
        }
    },
    config: {
        mixins: {
            /* TASK-6MKF0V: set-shipping/set-billing information mixins removed — they only
             * injected the retired sub_city extension attribute. */
            'Magento_Checkout/js/view/cart/shipping-estimation': {
                'Secomm_AddressDropdown/js/view/cart/shipping-estimation-mixin': true
            },
            /* DEC-8: Secomm_Ahamove mixin removed (module not present; project-specific leak). */
            'Magento_Checkout/js/model/shipping-save-processor/default': {
                'Secomm_AddressDropdown/js/model/shipping-save-processor/default-mixin': true
            },
            'Magento_Checkout/js/view/shipping-information/address-renderer/default': {
                'Secomm_AddressDropdown/js/view/shipping-information/address-renderer/default-mixin': true
            },
            'Magento_Checkout/js/view/billing-address': {
                'Secomm_AddressDropdown/js/view/billing-address-mixin': true
            },
            'Magento_Checkout/js/model/checkout-data-resolver': {
                'Secomm_AddressDropdown/js/model/checkout-data-resolver-mixin': true
            },
            'Magento_Checkout/js/view/shipping': {
                'Secomm_AddressDropdown/js/view/shipping-mixin': true
            }
        }
    }
};
