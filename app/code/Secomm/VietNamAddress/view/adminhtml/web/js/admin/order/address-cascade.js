define([
    'jquery',
    'mage/translate',
    'Secomm_AddressDropdown/js/form/schema-cascade',
    'domReady!'
], function ($, translate, schemaCascade) {
    'use strict';

    /*
     * Admin order create/edit address cascade (billing + shipping roots).
     *
     * TASK-9EX975 Slice A: the cascade is now SCHEMA-DRIVEN through the shared
     * Secomm_AddressDropdown factory — the number of city levels, their labels and
     * placeholders come from the address country's resolved Address Profile
     * (`addressSchema`), the options from `addressLocations`, with the storefront's
     * stop-at-leaf semantics. There is no `country == 'VN'` gate any more: a mapped
     * country renders its profile's levels (VN 2025 = ward, VN pre-2025 = district +
     * ward), an unmapped country keeps Magento's native city input. The class names
     * (secomm-vn-ward-*) are kept for layout compatibility; they now host the generic
     * cascade levels. The deepest selection persists into the native city input.
     *
     * Everything ORDER-FORM-SPECIFIC stays here: Prototype RegionUpdater quirks
     * (stale defaultValue clearing), the async hydration watcher (regionUpdater /
     * "select from existing customer addresses" / same-as-billing set values without
     * jQuery events), same-as-billing shipping lock (disabled fields copy billing), the
     * order lifecycle hook (window.order.fillAddressFields) and the MutationObserver
     * that binds address fragments replaced by AJAX.
     */

    var ROOT_SELECTOR = '#order-billing_address_fields, #order-shipping_address_fields, .form-inline, #edit_form';
    var CITY_FIELD_SELECTOR = 'input[name$="[city]"], input[name="city"]';
    var COUNTRY_FIELD_SELECTOR = 'select[name$="[country_id]"], input[name$="[country_id]"], select[name="country_id"], input[name="country_id"]';
    var REGION_FIELD_SELECTOR = 'select[name$="[region_id]"], input[name$="[region_id]"], select[name="region_id"], input[name="region_id"]';
    var WARD_SELECT_CLASS = 'secomm-vn-ward-select';
    var WARD_INPUT_CLASS = 'secomm-vn-ward-input';
    var WARD_VISIBLE_CLASS = 'secomm-vn-ward-visible';
    var BOUND_FLAG = 'secommVnBound';
    var CASCADE_FLAG = 'secommCityCascade';
    var refreshTimer = null;

    function getField($root, selector) {
        return $root.find(selector).first();
    }

    function getCountryField($root) {
        return getField($root, COUNTRY_FIELD_SELECTOR);
    }

    function getRegionField($root) {
        return getField($root, REGION_FIELD_SELECTOR);
    }

    function getCityField($root) {
        return getField($root, CITY_FIELD_SELECTOR);
    }

    function isShippingLocked($root) {
        if (!$root.is('#order-shipping_address_fields')) {
            return false;
        }

        // After an AJAX shipping-fragment replacement, Magento has already disabled
        // the address fields but the checkbox can be outside the new fragment briefly.
        // The disabled country field is therefore the reliable same-as-billing state.
        return $('#order-shipping_same_as_billing').is(':checked') ||
            getCountryField($root).is(':disabled');
    }

    function getBillingCityValue() {
        return $('#order-billing_address_fields').find(CITY_FIELD_SELECTOR).first().val() || '';
    }

    function refreshLockedShipping() {
        window.setTimeout(function () {
            var $shippingRoot = $('#order-shipping_address_fields');

            if ($shippingRoot.length && isShippingLocked($shippingRoot)) {
                refreshRoot($shippingRoot);
            }
        }, 0);
    }

    /**
     * Build (once) the cascade for an address root: a level-0 select injected after the
     * native city input, managed by the shared schema-cascade factory.
     */
    function ensureCascade($root) {
        var existing = $root.data(CASCADE_FLAG);

        if (existing) {
            return existing;
        }

        var $cityField = getCityField($root);
        var $citySelect = $('<select/>', {
            'class': 'admin__control-select ' + WARD_SELECT_CLASS,
            'data-secomm-vn-ward-select': '1'
        });

        $citySelect.insertAfter($cityField);

        var cascade = schemaCascade.createCascade({
            level0Select: $citySelect,
            injectAfter: $citySelect,
            selectClass: 'admin__control-select ' + WARD_SELECT_CLASS,
            getCountryId: function () {
                return getCountryField($root).val() || '';
            },
            getRegionId: function () {
                return getRegionField($root).val() || '';
            },
            getSavedName: function () {
                return $cityField.val() || '';
            },
            onLeaf: function (leafName) {
                $cityField.val(leafName || '');
                $cityField.trigger('change');
            },
            onSchemaResolved: function (hasSchema) {
                var locked = isShippingLocked($root);

                if (hasSchema) {
                    $cityField.addClass(WARD_INPUT_CLASS).hide().removeClass('required-entry');
                    $citySelect.addClass(WARD_VISIBLE_CLASS).show().addClass('required-entry');
                    $citySelect.prop('disabled', locked);
                } else {
                    $citySelect.removeClass(WARD_VISIBLE_CLASS).hide().prop('disabled', true).removeClass('required-entry');
                    $cityField.removeClass(WARD_INPUT_CLASS).show().addClass('required-entry');
                }
            },
            locked: function () {
                return isShippingLocked($root);
            },
            fallbackPlaceholder: $.mage.__('Please select a city')
        });

        $root.data(CASCADE_FLAG, cascade);

        return cascade;
    }

    function refreshRoot($root) {
        var $countryField = getCountryField($root);
        var $regionField = getRegionField($root);
        var $cityField = getCityField($root);

        if (!$countryField.length || !$regionField.length || !$cityField.length) {
            return;
        }

        var cascade = ensureCascade($root);

        // The shipping fragment can be rendered before its hidden native city input is
        // refreshed after a country switch. While Same As Billing is active, Billing is
        // the authoritative source, just like Magento's country and region fields.
        if (isShippingLocked($root)) {
            $cityField.val(getBillingCityValue());
        }

        cascade.refresh();
    }

    function bindRoot($root) {
        var $countryField;
        var $regionField;
        var $cityField;
        var cascade;

        if ($root.data(BOUND_FLAG)) {
            return;
        }

        $countryField = getCountryField($root);
        $regionField = getRegionField($root);
        $cityField = getCityField($root);

        if (!$countryField.length || !$regionField.length || !$cityField.length) {
            return;
        }

        cascade = ensureCascade($root);
        $root.data(BOUND_FLAG, true);

        // Magento's Prototype RegionUpdater keeps the form's initial region_id in the
        // select's `defaultValue` attribute. On a manual country change it reuses that
        // value while rebuilding the new country's options. Region ids are global, so a
        // US id can accidentally match a VN province (for example Can Tho City). Clear
        // the stale defaults during capture, before RegionUpdater receives the event.
        $countryField[0].addEventListener('change', function (event) {
            if (event.isTrusted === false) {
                return;
            }

            $regionField.removeAttr('defaultValue').prop('defaultValue', '');
            $regionField.val('');
            $root.find('input[name$="[region]"], input[name="region"]').first().val('');
            $cityField.val('');
            cascade.clear();
        }, true);

        // The order form's region_id <select> is populated/set asynchronously by the
        // Prototype regionUpdater (mage/adminhtml/form), and "Select from existing
        // customer addresses" + "Same as billing" set country/region programmatically —
        // none of those dispatch a jQuery change event. Track the last (country|region)
        // we acted on and share it between the manual handlers and the hydration watcher
        // below so a cascade refresh is triggered exactly once per change. The city
        // value is deliberately NOT in the key: the cascade itself writes it (onLeaf)
        // and keying on it would re-trigger a refresh the cascade already handled.
        var addressKey = function () {
            return ($countryField.val() || '') + '|' + ($regionField.val() || '');
        };
        var lastKey = addressKey();

        var apply = function () {
            lastKey = addressKey();
            refreshRoot($root);
        };

        // RegionUpdater uses Prototype events while the order form also binds jQuery
        // handlers. Listen natively as well, then wait one tick for the selected
        // province value to settle before requesting its city list.
        $regionField[0].addEventListener('change', function (event) {
            if (event.isTrusted === false) {
                return;
            }

            window.setTimeout(apply, 0);
        }, true);

        $countryField.off('change.secommVnWard').on('change.secommVnWard', function (event) {
            // Programmatic address hydration must retain both values for pre-selection.
            // Manual changes were reset in the capture listener before RegionUpdater ran.
            if (event.originalEvent) {
                cascade.clear();
            }

            apply();
            if ($root.is('#order-billing_address_fields')) {
                refreshLockedShipping();
            }
        });

        $regionField.off('change.secommVnWard').on('change.secommVnWard', function () {
            apply();
            if ($root.is('#order-billing_address_fields')) {
                refreshLockedShipping();
            }
        });

        $cityField.off('change.secommVnWard').on('change.secommVnWard', function () {
            if ($root.is('#order-billing_address_fields')) {
                refreshLockedShipping();
            }
        });

        refreshRoot($root);

        // Bounded hydration watcher: re-run the cascade when region_id/country_id change
        // programmatically (regionUpdater async hydrate on a saved/edit address,
        // selectAddress, same-as-billing copy). Stops after the form has settled (~30s);
        // later manual interaction keeps working via the change handlers above. Shares
        // lastKey so it never double-triggers a refresh the handlers already actioned.
        var ticks = 0;
        var maxTicks = 120; // ~30s at 250ms
        var hydrationTimer = setInterval(function () {
            ticks += 1;
            if (addressKey() !== lastKey) {
                apply();
            }
            if (ticks >= maxTicks) {
                clearInterval(hydrationTimer);
            }
        }, 250);
    }

    function scan() {
        var seen = {};

        $(CITY_FIELD_SELECTOR).each(function () {
            var $cityField = $(this);
            var $root = $cityField.closest('#order-billing_address_fields, #order-shipping_address_fields, .form-inline, #edit_form');
            var key;

            if (!$root.length) {
                return;
            }

            key = ($root.attr('id') || 'root') + '::' + ($cityField.attr('name') || '');

            if (seen[key]) {
                return;
            }

            seen[key] = true;
            bindRoot($root);
        });
    }

    function bindNewOrderFallback() {
        document.addEventListener('change', function (event) {
            var $field = $(event.target);
            var $root;

            if (!$field.is(COUNTRY_FIELD_SELECTOR) && !$field.is(REGION_FIELD_SELECTOR)) {
                return;
            }

            $root = $field.closest(ROOT_SELECTOR);
            if (!$root.length || $root.data(BOUND_FLAG)) {
                return;
            }

            // Order-create can replace the address markup after the first scan. Bind the
            // new fragment on its first country/region interaction instead of requiring
            // a page reload or relying on an inline form script being re-executed.
            bindRoot($root);
            window.setTimeout(function () {
                refreshRoot($root);
            }, 0);
        }, true);
    }

    function refreshBoundRoots() {
        $(CITY_FIELD_SELECTOR).each(function () {
            var $cityField = $(this);
            var $root = $cityField.closest(ROOT_SELECTOR);

            if ($root.length && $root.data(BOUND_FLAG)) {
                refreshRoot($root);
            }
        });
    }

    function scheduleRefresh() {
        window.clearTimeout(refreshTimer);
        refreshTimer = window.setTimeout(refreshBoundRoots, 0);
    }

    function bindOrderLifecycle() {
        var order = window.order;
        var originalFillAddressFields;

        if (!order || order.secommVnAddressCascadeBound) {
            return Boolean(order);
        }

        originalFillAddressFields = order.fillAddressFields;
        order.fillAddressFields = function () {
            var result = originalFillAddressFields.apply(this, arguments);

            // Customer-address selection populates fields programmatically. Refresh
            // after Magento's RegionUpdater has applied its country/region values.
            scheduleRefresh();
            return result;
        };
        order.secommVnAddressCascadeBound = true;

        return true;
    }

    function observe() {
        var debounceTimer = null;

        if (!window.MutationObserver) {
            window.setInterval(scan, 1500);
            return;
        }

        new MutationObserver(function () {
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(scan, 50);
        }).observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    return function init() {
        scan();
        observe();
        bindNewOrderFallback();

        // The create-order bootstrap can initialise window.order after this module.
        // Retry briefly; standalone address edit simply exits without the order object.
        var attempts = 0;
        var orderTimer = window.setInterval(function () {
            attempts += 1;
            if (bindOrderLifecycle() || attempts >= 40) {
                window.clearInterval(orderTimer);
            }
        }, 250);
    };
});
