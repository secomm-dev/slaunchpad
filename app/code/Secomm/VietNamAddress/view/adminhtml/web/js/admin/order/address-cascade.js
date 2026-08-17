define(['jquery', 'mage/translate', 'domReady!'], function ($) {
    'use strict';

    var ROOT_SELECTOR = '#order-billing_address_fields, #order-shipping_address_fields, .form-inline, #edit_form';
    var CITY_FIELD_SELECTOR = 'input[name$="[city]"], input[name="city"]';
    var COUNTRY_FIELD_SELECTOR = 'select[name$="[country_id]"], input[name$="[country_id]"], select[name="country_id"], input[name="country_id"]';
    var REGION_FIELD_SELECTOR = 'select[name$="[region_id]"], input[name$="[region_id]"], select[name="region_id"], input[name="region_id"]';
    var WARD_SELECT_CLASS = 'secomm-vn-ward-select';
    var WARD_INPUT_CLASS = 'secomm-vn-ward-input';
    var WARD_VISIBLE_CLASS = 'secomm-vn-ward-visible';
    var BOUND_FLAG = 'secommVnBound';
    var REQUEST_FLAG = 'secommVnWardRequestId';
    var refreshTimer = null;

    function escapeGraphQlValue(value) {
        return JSON.stringify(String(value || ''));
    }

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

    function getWardSelect($root) {
        return $root.find('.' + WARD_SELECT_CLASS).first();
    }

    function isVietnam(countryValue) {
        return String(countryValue || '').toUpperCase() === 'VN';
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

    function ensureWardSelect($root, $cityField) {
        var $wardSelect = getWardSelect($root);

        if ($wardSelect.length) {
            return $wardSelect;
        }

        $wardSelect = $('<select/>', {
            'class': 'admin__control-select ' + WARD_SELECT_CLASS,
            'data-secomm-vn-ward-select': '1'
        });

        $wardSelect.insertAfter($cityField);
        $wardSelect.on('change.secommVnWard', function () {
            $cityField.val($(this).val() || '');
            $cityField.trigger('change');
        });

        return $wardSelect;
    }

    function setWardVisibility($cityField, $wardSelect, isVn, isLocked) {
        if (isVn) {
            $cityField.addClass(WARD_INPUT_CLASS).hide();
            $wardSelect.addClass(WARD_VISIBLE_CLASS).show().prop('disabled', isLocked);
            $wardSelect.addClass('required-entry');
            $cityField.removeClass('required-entry');
        } else {
            $wardSelect.removeClass(WARD_VISIBLE_CLASS).hide().prop('disabled', true);
            $wardSelect.removeClass('required-entry');
            $cityField.removeClass(WARD_INPUT_CLASS).show().addClass('required-entry');
        }
    }

    function renderWardOptions($wardSelect, wards, selectedWard) {
        var placeholder = $.mage.__('Please select a city');
        var hasSelected = false;

        $wardSelect.empty();
        $wardSelect.append($('<option/>', {
            value: '',
            text: placeholder
        }));

        wards.forEach(function (ward) {
            var wardValue = ward.default_name || '';
            var wardLabel = ward.label || ward.default_name || wardValue;

            if (wardValue && wardValue === selectedWard) {
                hasSelected = true;
            }

            $wardSelect.append($('<option/>', {
                value: wardValue,
                text: wardLabel
            }));
        });

        if (!hasSelected) {
            $wardSelect.val('');
            return false;
        }

        $wardSelect.val(selectedWard);
        return true;
    }

    function loadWards($root, $cityField, $wardSelect, regionId, selectedWard, isLocked) {
        var requestId = (parseInt($root.data(REQUEST_FLAG), 10) || 0) + 1;
        var query;

        $root.data(REQUEST_FLAG, requestId);

        if (!regionId) {
            renderWardOptions($wardSelect, [], '');
            $cityField.val('');
            $wardSelect.prop('disabled', true).show();
            $cityField.hide();
            return $.Deferred().resolve().promise();
        }

        query = 'query { GetListCity(input: { region_id: ' + escapeGraphQlValue(regionId) + ', area: "adminhtml"}) { default_name label } }';
        $wardSelect.prop('disabled', true);

        return fetch('/graphql', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                query: query
            })
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Unable to load city options.');
                }

                return response.json();
            })
            .then(function (payload) {
                var wards = (payload && payload.data && payload.data.GetListCity) ? payload.data.GetListCity : [];
                var currentRequestId = parseInt($root.data(REQUEST_FLAG), 10) || 0;

                if (currentRequestId !== requestId) {
                    return;
                }

                if (payload && payload.errors) {
                    throw new Error('Unable to load city options.');
                }

                if (!wards.length) {
                    renderWardOptions($wardSelect, [], '');
                    $cityField.val('');
                    $wardSelect.prop('disabled', true).show();
                    $cityField.hide();
                    return;
                }

                if (!renderWardOptions($wardSelect, wards, selectedWard, false)) {
                    $cityField.val('');
                }

                $wardSelect.prop('disabled', isLocked).show();
                $cityField.hide();

                if ($wardSelect.val()) {
                    $cityField.val($wardSelect.val());
                }
            })
            .catch(function () {
                if ((parseInt($root.data(REQUEST_FLAG), 10) || 0) !== requestId) {
                    return;
                }

                renderWardOptions($wardSelect, [], '');
                $cityField.val('');
                $wardSelect.prop('disabled', true).show();
                $cityField.hide();
            });
    }

    function refreshRoot($root) {
        var $countryField = getCountryField($root);
        var $regionField = getRegionField($root);
        var $cityField = getCityField($root);
        var $wardSelect;
        var countryValue;
        var regionValue;
        var cityValue;
        var shippingLocked;

        if (!$countryField.length || !$regionField.length || !$cityField.length) {
            return;
        }

        $wardSelect = ensureWardSelect($root, $cityField);
        countryValue = $countryField.val();
        regionValue = $regionField.val();
        cityValue = $cityField.val();
        shippingLocked = isShippingLocked($root);

        // The shipping fragment can be rendered before its hidden native city input is
        // refreshed after a country switch. While Same As Billing is active, Billing is
        // the authoritative source, just like Magento's country and region fields.
        if (shippingLocked) {
            cityValue = getBillingCityValue();
            $cityField.val(cityValue);
        }

        setWardVisibility($cityField, $wardSelect, isVietnam(countryValue), shippingLocked);

        if (!isVietnam(countryValue)) {
            $wardSelect.val('');
            return;
        }

        loadWards($root, $cityField, $wardSelect, regionValue, cityValue, shippingLocked);
    }

    function bindRoot($root) {
        var $countryField;
        var $regionField;
        var $cityField;
        var $wardSelect;

        if ($root.data(BOUND_FLAG)) {
            return;
        }

        $countryField = getCountryField($root);
        $regionField = getRegionField($root);
        $cityField = getCityField($root);

        if (!$countryField.length || !$regionField.length || !$cityField.length) {
            return;
        }

        $wardSelect = ensureWardSelect($root, $cityField);
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
            $wardSelect.empty().prop('disabled', true);
        }, true);

        // The order form's region_id <select> is populated/set asynchronously by the
        // Prototype regionUpdater (mage/adminhtml/form), and "Select from existing
        // customer addresses" + "Same as billing" set country/region programmatically —
        // none of those dispatch a jQuery change event. Track the last (country|region)
        // we acted on and share it between the manual handlers and the hydration watcher
        // below so a ward load is triggered exactly once per change.
        var addressKey = function () {
            return ($countryField.val() || '') + '|' + ($regionField.val() || '') + '|' + ($cityField.val() || '');
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
            if (event.isTrusted === false || !isVietnam($countryField.val())) {
                return;
            }

            window.setTimeout(apply, 0);
        }, true);

        $countryField.off('change.secommVnWard').on('change.secommVnWard', function (event) {
            // Programmatic address hydration must retain both values for pre-selection.
            // Manual changes were reset in the capture listener before RegionUpdater ran.
            if (event.originalEvent) {
                $wardSelect.val('');
            }

            apply();
            if ($root.is('#order-billing_address_fields')) {
                refreshLockedShipping();
            }
        });

        $regionField.off('change.secommVnWard').on('change.secommVnWard', function () {
            if (isVietnam($countryField.val())) {
                apply();
            }
            if ($root.is('#order-billing_address_fields')) {
                refreshLockedShipping();
            }
        });

        $wardSelect.off('change.secommVnWard').on('change.secommVnWard', function () {
            $cityField.val($(this).val() || '').trigger('change');
            lastKey = addressKey();
        });

        $cityField.off('change.secommVnWard').on('change.secommVnWard', function () {
            if ($root.is('#order-billing_address_fields')) {
                refreshLockedShipping();
            }
        });

        refreshRoot($root);

        // Bounded hydration watcher: re-load wards when region_id/country_id change
        // programmatically (regionUpdater async hydrate on a saved/edit address,
        // selectAddress, same-as-billing copy). Stops after the form has settled (~30s);
        // later manual interaction keeps working via the change handlers above. Shares
        // lastKey so it never double-triggers a load the handlers already actioned.
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
