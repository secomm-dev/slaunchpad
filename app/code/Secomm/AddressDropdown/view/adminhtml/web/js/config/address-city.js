/**
 * SL-013 / FEAT-007 (DEC-019/025): generic, country-agnostic dependent dropdown driver for
 * the admin config `city` field (Store Information + Shipping Origin).
 *
 * Generic mechanism (Secomm_AddressDropdown). Wards/sub-cities are loaded via the generic
 * GraphQL resolvers GetListCity / GetListSubCity (area=adminhtml), filtered by region_id.
 * VN-specific behaviour/label/validate is owned by Secomm_VietNamAddress; this file holds
 * NO country=='VN' logic (data-driven: VN has 2 levels so sub_city stays hidden).
 *
 * Wired through data-mage-init on the renderer wrapper (.secomm-config-address-city).
 *
 * CONFIG-SPECIFIC: the config form's own region updater
 * (module-config/.../system/config/js.phtml) REPLACES the region_id DOM node via
 * `parentNode.innerHTML = select.outerHTML` when a country is chosen. A cached jQuery
 * reference therefore goes stale (detached) the moment a province list is rendered. To stay
 * correct we (1) re-query country/region_id FRESH on every read, and (2) bind change handlers
 * via event DELEGATION on the form so they survive the node replacement.
 */
define([
    'jquery'
], function ($) {
    'use strict';

    function escapeSelectorId(id) {
        return id ? '#' + id.replace(/([!"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1') : '';
    }

    function escapeGraphQlValue(value) {
        // Region/city values come from form fields; escape before interpolating into the
        // GraphQL query string (no injection).
        return String(value == null ? '' : value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    }

    function populateOptions($select, options, placeholder) {
        $select.empty();
        $select.append($('<option>', {value: '', text: placeholder}));

        options.forEach(function (option) {
            $select.append($('<option>', {
                value: option.default_name,
                text: option.label
            }));
        });
    }

    return function (config, element) {
        var $root = $(element);

        if ($root.data('secommConfigAddressCityInitialized')) {
            return;
        }
        $root.data('secommConfigAddressCityInitialized', true);

        // The config form ancestor. country/region_id live elsewhere in this form (NOT inside
        // our renderer wrapper), and region_id may be replaced under us — always re-query.
        var $form = $root.closest('form');
        if (!$form.length) {
            $form = $(document); // defensive fallback so the cascade still finds sibling fields
        }

        // The config form renders ALL sections in ONE form, so several address groups (e.g.
        // store_information + origin) coexist. Suffix selectors alone would match the wrong
        // group's fields. Derive this group's country/region ids from the city field's own id
        // (same `{group}_` prefix) so each renderer instance binds only to its own siblings.
        var baseId = (config.cityInputId || '').replace(/_city$/, '');

        function findSiblingBySuffix(suffix) {
            if (baseId) {
                var $exact = $form.find(escapeSelectorId(baseId + suffix)).first();
                if ($exact.length) {
                    return $exact;
                }
            }
            // Fallback: suffix search scoped to the closest section fieldset.
            return $root.closest('fieldset').find('[id$="' + suffix + '"]').first();
        }

        var countrySelector = baseId ? escapeSelectorId(baseId + '_country_id') : '[id$="_country_id"]';
        var regionSelector = baseId ? escapeSelectorId(baseId + '_region_id') : '[id$="_region_id"]';

        // These are OUR elements inside the renderer wrapper; they are never replaced, so a
        // cached reference is safe.
        var $cityInput = $root.find(escapeSelectorId(config.cityInputId));
        var $citySelect = $root.find(escapeSelectorId(config.citySelectId));
        var $subCitySelect = $root.find(escapeSelectorId(config.subCitySelectId));
        var $cityInputWrap = $cityInput.closest('.secomm-config-city-input');
        var $citySelectWrap = $citySelect.closest('.secomm-config-city-select');
        var $subCityWrap = $subCitySelect.closest('.secomm-config-subcity-select');

        var currentCity = config.currentCity || $cityInput.val() || '';
        var currentSubCity = config.currentSubCity || '';
        var cityRequest = 0;
        var subCityRequest = 0;

        // Re-query helpers: the config region updater replaces the region_id node, so a cached
        // reference goes stale. Always read the current node from the form (scoped to our group).
        function getCountryValue() {
            var $c = findSiblingBySuffix('_country_id');
            return $c.length ? ($c.val() || '') : '';
        }

        function getRegionIdValue() {
            var $r = findSiblingBySuffix('_region_id');
            if ($r.length && $r.val()) {
                return $r.val();
            }
            return '';
        }

        function setCityMode(useSelect) {
            $citySelectWrap.toggle(useSelect);
            $cityInputWrap.toggle(!useSelect);
        }

        function setSubCityMode(useSelect) {
            $subCityWrap.toggle(useSelect);
        }

        function resetSubCity() {
            $subCitySelect.empty().append(
                $('<option>', {value: '', text: $.mage.__('Please select a sub-city')})
            );
            setSubCityMode(false);
        }

        function loadSubCities(cityName, regionId, selectedSubCity) {
            if (!cityName || !regionId) {
                resetSubCity();
                return;
            }

            var requestId = ++subCityRequest;
            var query = [
                'query {',
                '  GetListSubCity(input: { default_name: "' + escapeGraphQlValue(cityName) +
                    '", area: "adminhtml", region_id: "' + escapeGraphQlValue(regionId) + '" }) {',
                '    default_name',
                '    label',
                '  }',
                '}'
            ].join('\n');

            fetch('/graphql', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({query: query})
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (requestId !== subCityRequest) {
                        return;
                    }

                    var subCities = (data.data && data.data.GetListSubCity) ? data.data.GetListSubCity : [];
                    if (!subCities.length) {
                        resetSubCity();
                        return;
                    }

                    setSubCityMode(true);
                    populateOptions($subCitySelect, subCities, $.mage.__('Please select a sub-city'));

                    if (selectedSubCity) {
                        $subCitySelect.val(selectedSubCity);
                    }
                })
                .catch(function () {
                    if (requestId === subCityRequest) {
                        resetSubCity();
                    }
                });
        }

        function loadCities(regionId, selectedCity, selectedSubCity) {
            if (!regionId) {
                currentCity = '';
                currentSubCity = '';
                $citySelect.empty();
                setCityMode(false);
                resetSubCity();
                return;
            }

            var requestId = ++cityRequest;
            var query = [
                'query {',
                '  GetListCity(input: { region_id: "' + escapeGraphQlValue(regionId) + '", area: "adminhtml" }) {',
                '    default_name',
                '    label',
                '  }',
                '}'
            ].join('\n');

            fetch('/graphql', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({query: query})
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (requestId !== cityRequest) {
                        return;
                    }

                    var cities = (data.data && data.data.GetListCity) ? data.data.GetListCity : [];
                    console.warn('[Secomm/CfgCity] loadCities', { regionId: regionId, count: cities.length, data: data });
                    if (!cities.length) {
                        currentSubCity = '';
                        $citySelect.empty();
                        setCityMode(false);
                        resetSubCity();
                        return;
                    }

                    populateOptions($citySelect, cities, $.mage.__('Please select a city'));
                    setCityMode(true);

                    if (selectedCity && $citySelect.find('option').filter(function () {
                        return $(this).val() === selectedCity;
                    }).length) {
                        $citySelect.val(selectedCity);
                        $cityInput.val(selectedCity);
                        loadSubCities(selectedCity, regionId, selectedSubCity);
                        return;
                    }

                    if (selectedCity) {
                        // Stored value not in loaded options (data changed): keep it as free
                        // text rather than silently dropping it.
                        setCityMode(false);
                        $cityInput.val(selectedCity);
                        resetSubCity();
                        return;
                    }

                    $citySelect.val('');
                    $cityInput.val('');
                    resetSubCity();
                })
                .catch(function () {
                    if (requestId === cityRequest) {
                        setCityMode(false);
                        resetSubCity();
                    }
                });
        }

        $citySelect.on('change', function () {
            var selectedCity = $(this).val();
            $cityInput.val(selectedCity);
            currentCity = selectedCity;

            if (selectedCity) {
                currentSubCity = '';
                loadSubCities(selectedCity, getRegionIdValue(), '');
            } else {
                currentSubCity = '';
                resetSubCity();
            }
        });

        $subCitySelect.on('change', function () {
            currentSubCity = $(this).val();
        });

        function reloadCities() {
            loadCities(getRegionIdValue(), $cityInput.val() || currentCity, $subCitySelect.val() || currentSubCity);
        }

        function clearCitySelection() {
            $cityInput.val('');
            $citySelect.val('');
            currentCity = '';
            currentSubCity = '';
            resetSubCity();
        }

        // Event DELEGATION on the form (scoped to this group's ids): the config region updater
        // replaces the region_id node, so a direct bind on it would be lost. Delegated handlers
        // survive the replacement.
        $form.on('change', countrySelector, function (event) {
            console.warn('[Secomm/CfgCity] country change handler', { baseId: baseId, value: getCountryValue() });
            if (!event.originalEvent) {
                return; // programmatic - watcher reloads
            }
            // User changed country: previous province + ward are invalid. Reset the (current)
            // region field and clear city; the watcher reloads once the region updater runs.
            var $r = findSiblingBySuffix('_region_id');
            if ($r.length) {
                $r.val('');
            }
            clearCitySelection();
        });

        $form.on('change', regionSelector, function (event) {
            console.warn('[Secomm/CfgCity] region change handler', { baseId: baseId, value: getRegionIdValue() });
            if (!event.originalEvent) {
                return; // programmatic (region updater) - watcher reloads
            }
            // User picked a province: the old ward is invalid for it.
            clearCitySelection();
            reloadCities();
        });

        console.warn('[Secomm/CfgCity] init', {
            cityInputFound: $cityInput.length,
            citySelectFound: $citySelect.length,
            formFound: $form.length,
            countryId: $form.find('[id$="_country_id"]').first().attr('id'),
            regionIdEl: $form.find('[id$="_region_id"]').first().attr('id'),
            countryVal: getCountryValue(),
            regionVal: getRegionIdValue()
        });

        // Hydration watcher. The config region updater fetches provinces asynchronously and
        // rebuilds the region_id node (no jQuery change event). Re-query (country, region_id)
        // fresh each tick so the ward list follows the real, current province. Bounded; a user
        // country/region change above clears the selection, a programmatic change preserves it.
        var watchKey = function () {
            return (getCountryValue() || '') + '|' + (getRegionIdValue() || '');
        };
        var lastKey = watchKey();
        var watchTicks = 0;
        var watchTimer = setInterval(function () {
            watchTicks += 1;
            var key = watchKey();

            if (key !== lastKey) {
                lastKey = key;
                console.warn('[Secomm/CfgCity] watcher key changed -> reloadCities()', { key: key, country: getCountryValue(), region: getRegionIdValue() });
                reloadCities();
            }

            if (watchTicks >= 1200) { // ~5min at 250ms - covers editing a config form
                clearInterval(watchTimer);
            }
        }, 250);

        loadCities(getRegionIdValue(), currentCity, currentSubCity);
    };
});
