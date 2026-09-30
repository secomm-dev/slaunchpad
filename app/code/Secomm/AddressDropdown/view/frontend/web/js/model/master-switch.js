/**
 * Secomm_AddressDropdown — TASK-SEC-A5 master switch client helper.
 *
 * The server injects `secommAddressDropdownEnabled` into window.checkoutConfig (see
 * Plugin\Checkout\DefaultConfigProviderPlugin). When the module is disabled for the current
 * store scope, every RequireJS mixin and dropdown component must behave EXACTLY like it is
 * absent: native Magento address fields and native checkout behavior take over — no AJAX,
 * no GraphQL, no custom fields, no DOM mutation.
 */
define(function () {
    'use strict';

    return function isEnabled() {
        return window.checkoutConfig !== undefined
            && window.checkoutConfig !== null
            && window.checkoutConfig.secommAddressDropdownEnabled === true;
    };
});
