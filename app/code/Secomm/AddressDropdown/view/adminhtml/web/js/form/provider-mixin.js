define([
    'jquery',
    'mage/utils/wrapper',
    'mage/validation',
], function ($, wrapper) {
    'use strict';

    /*
     * Secomm_AddressDropdown — generic, country-agnostic, data-driven multi-level
     * address cascade for the admin customer-address form (DEC-019/025).
     *
     * SL-011: this mixin owns ONLY the generic mechanism (region -> city -> sub_city,
     * options sourced from the generic GraphQL resolvers GetListCity / GetListSubCity).
     * It contains NO `country == 'VN'` gate and NO VN-specific labels — VN behaviour
     * lives in Secomm_VietNamAddress. For a country with no city/ward data the cascade
     * falls back to Magento's native `city` text input; `sub_city` only renders when a
     * country has 3-level data (data-driven per DEC-025).
     *
     * The form is loaded via AJAX into the customer_address_update_modal
     * (Magento_Customer insertForm), which destroys and re-renders the whole form on
     * every open — so each provider instance owns exactly one address form. Every
     * selector is therefore resolved against that form's root (the closest fieldset that
     * also holds region_id), never globally, and a per-root guard prevents the rare case
     * of a stale provider re-binding a newer form. The initial city load waits briefly
     * for region_id to be hydrated by the region UI component (it may populate after
     * city_select renders in the AJAX modal); Add-new (no region yet) relies on the
     * region change handler. The saved city/ward is read from the provider data so the
     * Edit pre-select is robust even if the native city input lags hydration.
     */

    // Escape an interpolated GraphQL argument the same way the admin order cascade
    // (Secomm_VietNamAddress/.../address-cascade.js) does — value is JSON-encoded so any
    // embedded quote/backslash/control char is neutralised.
    function escapeGraphQlValue(value) {
        return JSON.stringify(String(value || ''));
    }

    return function (Provider) {
        return Provider.extend({
            initialize: function () {
                this._super();
                let self = this;

                let subCity = (self.data && self.data.sub_city) ? self.data.sub_city : '';
                let savedCity = (self.data && self.data.city) ? self.data.city : '';

                self.SELECTORS = {
                    COUNTRY_ID: '[name="country_id"]',
                    REGION_ID: '[name="region_id"]',
                    CITY_SELECT: 'select[name="city_select"]',
                    CITY_SELECT_CONTAINER: '[data-index="city_select"]',
                    CITY_INPUT: 'input[name="city"]',
                    CITY_INPUT_CONTAINER: '[data-index="city"]',
                    SUB_CITY_SELECT: '[name="sub_city"]',
                    SUB_CITY_CONTAINER: '[data-index="sub_city"]'
                };

                // Poll until this form's city_select renders, then wire that form's cascade.
                self._setupInterval = setInterval(function () {
                    let $citySelect = $(self.SELECTORS.CITY_SELECT);
                    if ($citySelect.length) {
                        clearInterval(self._setupInterval);
                        self._setupInterval = null;
                        self._setupCascade($citySelect.first(), subCity, savedCity);
                    }
                }, 500);

                return this;
            },

            /**
             * Clear the polling timers when the AJAX form is torn down by insertForm, so a
             * destroyed provider never binds a later form. Best-effort — teardown is non-fatal.
             */
            destroy: function () {
                try {
                    if (this._setupInterval) {
                        clearInterval(this._setupInterval);
                        this._setupInterval = null;
                    }
                    if (this._regionTimer) {
                        clearInterval(this._regionTimer);
                        this._regionTimer = null;
                    }
                } catch (error) {
                    // ignore — destroy must never throw
                }
                return this._super();
            },

            /**
             * The nearest ancestor of a field that also holds the region field is this
             * form's root. Falls back to <form>, then document.
             */
            _resolveRoot: function ($el) {
                let selectors = this.SELECTORS;
                let candidates = [$el.closest('fieldset'), $el.closest('form'), $(document)];

                for (let i = 0; i < candidates.length; i++) {
                    if (candidates[i].length && candidates[i].find(selectors.REGION_ID).length) {
                        return candidates[i];
                    }
                }
                return $(document);
            },

            /**
             * Bind the generic cascade once the customer-address form is present.
             * Guarded per-root so a re-rendered/re-opened form is wired at most once.
             */
            _setupCascade: function ($citySelect, subCity, savedCity) {
                let self = this;
                let $root = self._resolveRoot($citySelect);

                if ($root.data('secommCascadeBound')) {
                    return;
                }
                $root.data('secommCascadeBound', true);

                self.fields = {
                    $root: $root,
                    $countryId: $root.find(self.SELECTORS.COUNTRY_ID).first(),
                    $regionId: $root.find(self.SELECTORS.REGION_ID).first(),
                    $citySelect: $root.find(self.SELECTORS.CITY_SELECT).first(),
                    $citySelectContainer: $root.find(self.SELECTORS.CITY_SELECT_CONTAINER).first(),
                    $cityInput: $root.find(self.SELECTORS.CITY_INPUT).first(),
                    $cityInputContainer: $root.find(self.SELECTORS.CITY_INPUT_CONTAINER).first(),
                    $subCitySelect: $root.find(self.SELECTORS.SUB_CITY_SELECT).first(),
                    $subCityContainer: $root.find(self.SELECTORS.SUB_CITY_CONTAINER).first()
                };

                let f = self.fields;

                // Seed old-values from the saved record so Edit can re-select them.
                let currentCity = savedCity || f.$cityInput.val();
                f.$citySelect.attr('old-value', currentCity);
                f.$subCitySelect.attr('old-value', subCity);

                // city (generic) change -> persist into native city + load sub-cities.
                f.$citySelect.on('change', function () {
                    let selectedCityId = $(this).val();
                    if (selectedCityId !== '-') {
                        f.$cityInput.val(selectedCityId).change();
                        self._loadSubCities(selectedCityId);
                    }
                });

                // region change -> reload cities for the new region (data-driven).
                f.$regionId.on('change', function () {
                    let selectedRegionId = f.$regionId.val();
                    f.$subCityContainer.hide();
                    f.$cityInput.val('');
                    f.$citySelect.val('').change();
                    self._loadCities(selectedRegionId, '');
                });

                // country change -> reset the cascade (generic).
                f.$countryId.on('change', function () {
                    f.$regionId.val('');
                    f.$citySelect.empty();
                    f.$cityInput.val('');
                    f.$subCitySelect.empty();
                    f.$subCityContainer.hide();
                    f.$citySelect.val('').change();
                });

                // Initial load. region_id may be hydrated after city_select in the AJAX
                // modal, so wait briefly for it (Edit pre-select path); Add-new relies on
                // the region change handler above.
                f.$subCityContainer.hide();
                f.$cityInput.val('');
                self._loadCitiesWhenReady(currentCity);
            },

            /**
             * Wait (bounded) for region_id to populate, then load cities once. The region
             * UI component sets the saved value during the AJAX render, not necessarily
             * before city_select appears, so a single read at setup time would miss it.
             */
            _loadCitiesWhenReady: function (oldValueCity) {
                let self = this;
                let attempts = 0;
                let maxAttempts = 12; // ~3s at 250ms

                self._regionTimer = setInterval(function () {
                    let regionId = self.fields.$regionId.val();
                    if (regionId) {
                        clearInterval(self._regionTimer);
                        self._regionTimer = null;
                        self._loadCities(regionId, oldValueCity);
                    } else if (++attempts >= maxAttempts) {
                        clearInterval(self._regionTimer);
                        self._regionTimer = null;
                    }
                }, 250);
            },

            _loadCities: function (regionId, oldValueCity) {
                let self = this;
                let f = self.fields;

                // Drop a city response superseded by a newer region selection.
                self._cityReqId = (self._cityReqId || 0) + 1;
                let reqId = self._cityReqId;

                let query = `
                    query {
                        GetListCity(input: { region_id: ${escapeGraphQlValue(regionId)}, area: "adminhtml"}) {
                            default_name
                            label
                        }
                    }
                `;

                $('body').loader('show');

                fetch('/graphql', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ query: query })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (reqId !== self._cityReqId) {
                            return;
                        }
                        f.$citySelect.empty();
                        if (data.data && data.data.GetListCity && data.data.GetListCity.length > 0) {
                            self._updateCityDropdown(data.data.GetListCity, oldValueCity);
                            f.$citySelectContainer.show();
                            f.$cityInputContainer.hide();
                            let oldValueSubCity = f.$subCitySelect.attr('old-value');
                            self._loadSubCities(oldValueCity, oldValueSubCity);
                        } else {
                            f.$citySelectContainer.hide();
                            f.$citySelect.append($('<option></option>').attr('value', '-').text($.mage.__('Select a city')));
                            f.$citySelect.val('-').change();
                            f.$cityInputContainer.show();
                            self._loadSubCities('');
                        }
                    })
                    .catch(error => {
                        f.$citySelectContainer.hide();
                        f.$cityInputContainer.show();
                    })
                    .finally(() => {
                        $('body').loader('hide');
                    });
            },

            _updateCityDropdown: function (cities, selectedCity) {
                let self = this;

                let citySelect = self.fields.$citySelect;

                citySelect.empty();
                citySelect.append($('<option></option>').attr('value', '').text($.mage.__('Select a city')));

                cities.forEach(function (city) {
                    let option = $('<option></option>')
                        .attr('value', city.default_name)
                        .text(city.label);
                    citySelect.append(option);
                });

                if (selectedCity) {
                    citySelect.val(selectedCity).change();
                }
            },

            _loadSubCities: function (cityId, currentSubCity) {
                let self = this;
                let f = self.fields;
                let regionId = f.$regionId.val();
                let query = `
                    query {
                        GetListSubCity(input: { default_name: ${escapeGraphQlValue(cityId)} , area: "adminhtml", region_id: ${escapeGraphQlValue(regionId)}}) {
                            default_name
                            label
                        }
                    }
                `;

                $('body').loader('show');

                fetch('/graphql', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ query: query })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.data && data.data.GetListSubCity) {
                            if (data.data.GetListSubCity.length === 0) {
                                f.$subCityContainer.hide();
                                f.$subCitySelect.val('').change();
                            } else {
                                f.$subCityContainer.show();
                                //select first sub city
                                let oldValueCity = f.$subCitySelect.attr('old-value');
                                if (!currentSubCity && !oldValueCity || !data.data.GetListSubCity.find(element => element.default_name === oldValueCity)) {
                                    currentSubCity = data.data.GetListSubCity[0].default_name;
                                } else {
                                    currentSubCity = oldValueCity;
                                }
                            }
                            self._updateSubCityDropdown(data.data.GetListSubCity, currentSubCity);
                        } else {
                            f.$subCityContainer.show();
                            console.error('Error fetching sub cities:', data.errors);
                        }
                    })
                    .catch(error => {
                        console.error('Request failed:', error);
                    })
                    .finally(() => {
                        $('body').loader('hide');
                    });
            },

            _updateSubCityDropdown: function (subCities, selectedSubCity) {
                let self = this;

                let subCitySelect = self.fields.$subCitySelect;

                subCitySelect.empty();

                subCities.forEach(function (subCity) {
                    let option = $('<option></option>')
                        .attr('value', subCity.default_name)
                        .text(subCity.label);
                    subCitySelect.append(option);
                });

                if (selectedSubCity) {
                    subCitySelect.val(selectedSubCity).change();
                }
            }
        });
    };
});
