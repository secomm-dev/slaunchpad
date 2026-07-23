/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'mage/template',
    'underscore',
    'jquery-ui-modules/widget',
    'mage/validation',
    'mage/loader' // Ensure loader is included
], function ($, mageTemplate, _, widget, validation) {
    'use strict';
    $.widget('mage.directoryAddressDropdownUpdater', {
        options: {
            currentCity: '',
            currentSubCity: ''
        },

        /**
         * Initialize widget.
         * @private
         */
        _create: function () {
            let self = this;

            let regionInterval = setInterval(() => {
                if ($('[name="region_id"]')){
                    if ($('#sub-city-container').find('option').length > 1) {
                        $('#sub-city-container').show();
                    } else {
                        $('#sub-city-container').hide();
                    }
                    // Event binding for region_id change
                    $('#region_id').change(function () {
                        let selectedRegionId = $(this).val();
                        $('#sub-city-container').hide();
                        $('#city-input').val('');
                        self._loadCities(selectedRegionId);
                    });
                    // Event binding for country_id change
                    $('#country').change(function () {
                        // Reset region, city, and sub-city when country changes
                        $('#region_id').val('');
                        $('#city-select').empty();
                        $('#city-input').val('');
                        $('#sub-city-select').empty();
                        $('#sub-city-input').val('');
                        $('#sub-city-container').hide();

                        let selectedCountryId = $(this).val();
                        self._loadRegions(selectedCountryId);
                    });

                    // Event binding for city change
                    $('#city-select').change(function () {
                        let selectedCityId = $(this).val();
                        $('#city-input').val(selectedCityId);
                        self._loadSubCities(selectedCityId);
                    });

                    // Load cities if currentCity is set
                    let initialRegionId = $('#region_id').val();
                    this._loadCities(initialRegionId, this.options.currentCity);
                    clearInterval(regionInterval);
                }
            }, 500);
        },

        /**
         * Load cities based on regionId.
         * @private
         * @param {String} regionId - The region ID to load cities for.
         * @param {String} [currentCity] - The current city to set as selected (optional).
         */
        _loadCities: function (regionId, currentCity) {
            let self = this;
            let query = `
                query {
                    GetListCity(input: { region_id: "${regionId}" }) {
                        city_id
                        default_name
                        label
                    }
                }
            `;

            // Show loader
            $('body').loader('show');

            $.ajax({
                url: '/graphql',  // Update the URL to your Magento GraphQL endpoint
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ query: query }),
                success: function(response) {
                    if (response.data && response.data.GetListCity) {
                        self._updateCityDropdown(response.data.GetListCity, currentCity);
                    } else {
                        let cityInput = $('#city-input');
                        citySelect.hide();
                        cityInput.show();
                    }
                },
                error: function(xhr, status, error) {
                },
                complete: function() {
                    // Hide loader
                    $('body').loader('hide');
                }
            });
        },

        /**
         * Load regions based on countryId.
         * @private
         * @param {String} countryId - The country ID to load regions for.
         */
        _loadRegions: function (countryId) {
            let self = this;
            let query = `
                query {
                    GetListRegion(input: { country_id: "${countryId}" }) {
                        region_id
                        default_name
                        label
                    }
                }
            `;

            // Show loader
            $('body').loader('show');

            $.ajax({
                url: '/graphql',  // Update the URL to your Magento GraphQL endpoint
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ query: query }),
                success: function(response) {
                    if (response.data && response.data.GetListRegion) {
                        self._updateRegionDropdown(response.data.GetListRegion);
                    } else {
                        self._updateCityDropdown([], '');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Request failed:', error);
                },
                complete: function() {
                    // Hide loader
                    $('body').loader('hide');
                }
            });
        },

        /**
         * Load sub-cities based on cityId.
         * @private
         * @param {String} cityId - The city ID to load sub-cities for.
         * @param {String} [currentSubCity] - The current sub-city to set as selected (optional).
         */
        _loadSubCities: function (cityId, currentSubCity) {
            let self = this;
            let regionId = $('#region_id').val();
            let query = `
                query {
                    GetListSubCity(input: { default_name: "${cityId}", region_id: "${regionId}" }) {
                        sub_city_id
                        default_name
                        label
                    }
                }
            `;

            // Show loader
            $('body').loader('show');

            $.ajax({
                url: '/graphql',  // Update the URL to your Magento GraphQL endpoint
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ query: query }),
                success: function(response) {
                    if (response.data && response.data.GetListSubCity) {
                        if (response.data.GetListSubCity.length === 0) {
                            $('#sub-city-container').hide();
                        } else {
                            $('#sub-city-container').show();
                        }
                        self._updateSubCityDropdown(response.data.GetListSubCity, currentSubCity);
                    } else {
                        $('#sub-city-container').show(); // Show sub-city container even if no sub-cities are available
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Request failed:', error);
                },
                complete: function() {
                    // Hide loader
                    $('body').loader('hide');
                }
            });
        },

        /**
         * Update city dropdown with new data and set selected city.
         * @private
         * @param {Array} cities - The list of cities to populate the dropdown.
         * @param {String} [currentCity] - The current city to set as selected (optional).
         */
        _updateCityDropdown: function (cities, currentCity) {
            let citySelect = $('#city-select'); // Assuming this is your city dropdown element

            citySelect.empty();

            if (cities.length > 0) {
                citySelect.append($('<option></option>').attr('value', '').text($.mage.__('Select a city')));

                cities.forEach(function(city) {
                    let option = $('<option></option>')
                        .attr('value', city.default_name)
                        .text(city.label);
                    citySelect.append(option);
                });

                if (currentCity) {
                    citySelect.val(currentCity);
                    this._loadSubCities(currentCity, this.options.currentSubCity); // Load sub-cities if current city is set
                }

                citySelect.show();
                $('#city-input').hide(); // Assuming you have an input field for city selection
            } else {
                citySelect.hide();
                $('#city-input').show(); // Show input field if no cities are available
            }
        },

        /**
         * Update sub-city dropdown with new data and set selected sub-city.
         * @private
         * @param {Array} subCities - The list of sub-cities to populate the dropdown.
         * @param {String} [currentSubCity] - The current sub-city to set as selected (optional).
         */
        _updateSubCityDropdown: function (subCities, currentSubCity) {
            let subCitySelect = $('#sub-city-select'); // Assuming this is your sub-city dropdown element

            subCitySelect.empty();

            if (subCities.length > 0) {
                subCitySelect.append($('<option></option>').attr('value', '').text($.mage.__('Select a sub-city')));

                subCities.forEach(function(subCity) {
                    let option = $('<option></option>')
                        .attr('value', subCity.default_name)
                        .text(subCity.label);
                    subCitySelect.append(option);
                });

                if (currentSubCity) {
                    subCitySelect.val(currentSubCity);
                }

                subCitySelect.show();
                subCitySelect.prop('disabled', false);
                $('#sub-city-input').hide(); // Assuming you have an input field for sub-city selection
            } else {
                subCitySelect.hide();
                subCitySelect.prop('disabled', true);
                $('#sub-city-input').show(); // Show input field if no sub-cities are available
            }
        },

        /**
         * Update region dropdown with new data.
         * @private
         * @param {Array} regions - The list of regions to populate the dropdown.
         */
        _updateRegionDropdown: function (regions) {
            let regionSelect = $('#region_id'); // Assuming this is your region dropdown element

            regionSelect.empty();

            if (regions.length > 0) {
                regionSelect.append($('<option></option>').attr('value', '').text($.mage.__('Select a region')));

                regions.forEach(function(region) {
                    let option = $('<option></option>')
                        .attr('value', region.region_id)
                        .text(region.label);
                    regionSelect.append(option);
                });

                regionSelect.show();
            } else {
                regionSelect.hide();
            }
        }
    });

    return $.mage.directoryAddressDropdownUpdater;
});
