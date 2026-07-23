define([
    'jquery',
    'mage/utils/wrapper',
    'mage/validation',
], function ($, wrapper) {
    'use strict';

    return function (Provider) {
        return Provider.extend({
            initialize: function () {
                this._super();
                let self = this;

                let subCity = (self.data && self.data.sub_city) ? self.data.sub_city : '';

                self.SELECTORS = {
                    ADDRESS_EDIT_FORM: '.customer_form_areas_address_address_customer_address_update_modal',
                    COUNTRY_ID: '[name="country_id"]',
                    REGION_ID: '[name="region_id"]',
                    CITY_SELECT: 'select[name="city_select"]',
                    CITY_SELECT_CONTAINER: '[data-index="city_select"]',

                    CITY_INPUT: 'input[name="city"]',
                    CITY_INPUT_CONTAINER: '[data-index="city"]',
                    SUB_CITY_SELECT: '[name="sub_city"]',
                    SUB_CITY_CONTAINER: '[data-index="sub_city"]'
                };

                const COUNTRY_ID = $(self.SELECTORS.COUNTRY_ID);
                const REGION_ID = $(self.SELECTORS.REGION_ID);
                const CITY_SELECT = $(self.SELECTORS.CITY_SELECT);
                const CITY_INPUT = $(self.SELECTORS.CITY_INPUT);
                const SUB_CITY_SELECT = $(self.SELECTORS.SUB_CITY_SELECT);
                const SUB_CITY_CONTAINER = $(self.SELECTORS.SUB_CITY_CONTAINER);

                let cityInterval = setInterval(function () {
                    if ($(self.SELECTORS.ADDRESS_EDIT_FORM).find($(self.SELECTORS.CITY_SELECT)).length) {
                        let currentCity = $(self.SELECTORS.ADDRESS_EDIT_FORM).find($(self.SELECTORS.CITY_INPUT)).val();
                        $(self.SELECTORS.ADDRESS_EDIT_FORM).find($(self.SELECTORS.CITY_SELECT)).attr('old-value', currentCity);
                        $(self.SELECTORS.ADDRESS_EDIT_FORM).find($(self.SELECTORS.SUB_CITY_SELECT)).attr('old-value', subCity);
                        let initialRegionId = REGION_ID.val();
                        if (initialRegionId) {
                            self._loadCities(initialRegionId);
                        }
                        $(self.SELECTORS.ADDRESS_EDIT_FORM).find($(self.SELECTORS.CITY_SELECT)).change(function () {
                            let selectedCityId = $(this).val();
                            if (selectedCityId !== '-') {
                                $(self.SELECTORS.ADDRESS_EDIT_FORM).find($(self.SELECTORS.CITY_INPUT)).val(selectedCityId).change();
                                self._loadSubCities(selectedCityId);
                            }
                        });
                        clearInterval(cityInterval);
                    }
                }, 1000);
                let regionInterval = setInterval(function () {
                    if ($(self.SELECTORS.REGION_ID).length) {
                        $(self.SELECTORS.REGION_ID).on('change', function () {
                            let selectedRegionId = $(self.SELECTORS.REGION_ID).val();
                            SUB_CITY_CONTAINER.hide();
                            CITY_INPUT.val('');
                            $(self.SELECTORS.ADDRESS_EDIT_FORM).find($(self.SELECTORS.CITY_SELECT)).val('').change();
                            self._loadCities(selectedRegionId,'');
                        });
                        let selectedRegionId = $(self.SELECTORS.REGION_ID).val();
                        SUB_CITY_CONTAINER.hide();
                        CITY_INPUT.val('');
                        let oldValueCity = $(self.SELECTORS.ADDRESS_EDIT_FORM).find($(self.SELECTORS.CITY_SELECT)).attr('old-value');
                        self._loadCities(selectedRegionId,oldValueCity);
                        clearInterval(regionInterval);

                    }
                }, 1000);

                COUNTRY_ID.on('change', function () {
                    REGION_ID.val('');
                    CITY_SELECT.empty();
                    CITY_INPUT.val('');
                    SUB_CITY_SELECT.empty();
                    SUB_CITY_CONTAINER.hide();
                    $(self.SELECTORS.ADDRESS_EDIT_FORM).find($(self.SELECTORS.CITY_SELECT)).val('').change();
                });

                let setSubCityRequired = setInterval(function () {
                    if ($(self.SELECTORS.SUB_CITY_CONTAINER).length > 0) {
                        $(self.SELECTORS.SUB_CITY_CONTAINER).addClass("required");
                        clearInterval(setSubCityRequired);
                    }
                }, 1000)

                return this;
            },

            _loadCities: function (regionId,oldValueCity) {
                let self = this;
                let query = `
                    query {
                        GetListCity(input: { region_id: "${regionId}", area: "adminhtml"}) {
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
                        $(self.SELECTORS.ADDRESS_EDIT_FORM).find($(self.SELECTORS.CITY_SELECT)).empty();
                        if (data.data && data.data.GetListCity && data.data.GetListCity.length > 0) {
                            self._updateCityDropdown(data.data.GetListCity, oldValueCity);
                            $(self.SELECTORS.CITY_SELECT_CONTAINER).show();
                            $(self.SELECTORS.CITY_INPUT_CONTAINER).hide();
                            let oldValueSubCity = $(self.SELECTORS.ADDRESS_EDIT_FORM).find($(self.SELECTORS.SUB_CITY_SELECT)).attr('old-value');
                            self._loadSubCities(oldValueCity,oldValueSubCity);
                        } else {
                            $(self.SELECTORS.CITY_SELECT_CONTAINER).hide();
                            $(self.SELECTORS.CITY_SELECT).append($('<option></option>').attr('value', '-').text($.mage.__('Select a city')));
                            $(self.SELECTORS.CITY_SELECT).val('-').change();
                            $(self.SELECTORS.CITY_INPUT_CONTAINER).show();
                            self._loadSubCities('');

                        }
                    })
                    .catch(error => {
                        $(self.SELECTORS.CITY_SELECT_CONTAINER).hide();
                        $(self.SELECTORS.CITY_INPUT_CONTAINER).show();
                    })
                    .finally(() => {
                        $('body').loader('hide');
                    });
            },

            _updateCityDropdown: function (cities, selectedCity) {
                let self = this

                let citySelect = $(self.SELECTORS.ADDRESS_EDIT_FORM).find($(self.SELECTORS.CITY_SELECT));

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
                let regionId = $(self.SELECTORS.ADDRESS_EDIT_FORM).find($(self.SELECTORS.REGION_ID)).val();
                let query = `
                    query {
                        GetListSubCity(input: { default_name: "${cityId}" , area: "adminhtml", region_id: "${regionId}"}) {
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
                                $(self.SELECTORS.SUB_CITY_CONTAINER).hide();
                                $(self.SELECTORS.SUB_CITY_SELECT).val('').change();
                            } else {
                                $(self.SELECTORS.SUB_CITY_CONTAINER).show();
                                //select first sub city
                                let oldValueCity = $(self.SELECTORS.ADDRESS_EDIT_FORM).find($(self.SELECTORS.SUB_CITY_SELECT)).attr('old-value');
                                if (!currentSubCity && !oldValueCity || !data.data.GetListSubCity.find(element => element.default_name === oldValueCity)) {
                                    currentSubCity = data.data.GetListSubCity[0].default_name;
                                } else {
                                    currentSubCity = oldValueCity;
                                }
                            }
                            self._updateSubCityDropdown(data.data.GetListSubCity, currentSubCity);
                        } else {
                            $(self.SELECTORS.SUB_CITY_CONTAINER).show();
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

                let subCitySelect = $(self.SELECTORS.SUB_CITY_SELECT);

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
