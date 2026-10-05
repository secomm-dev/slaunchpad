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
    'mage/loader', // Ensure loader is included
    'Secomm_AddressDropdown/js/model/address-location-cache'
], function ($, mageTemplate, _, widget, validation, loader, locationCache) {
    'use strict';
    $.widget('mage.directoryAddressDropdownUpdater', {
        options: {
            currentCity: ''
        },

        /**
         * Initialize widget.
         * @private
         */
        _create: function () {
            let self = this;

            let regionInterval = setInterval(() => {
                if ($('[name="region_id"]').length){
                    // Event binding for region_id change
                    $('#region_id').change(function () {
                        let selectedRegionId = $(this).val();
                        $('#city-input').val('');
                        self._loadCities(selectedRegionId);
                    });
                    // Event binding for country_id change
                    $('#country').change(function () {
                        // Reset region and city when country changes
                        $('#region_id').val('');
                        $('#city-select').empty();
                        $('#city-input').val('');

                        let selectedCountryId = $(this).val();
                        self._loadRegions(selectedCountryId);
                    });

                    // Event binding for city change
                    $('#city-select').change(function () {
                        let selectedCityId = $(this).val();
                        $('#city-input').val(selectedCityId);
                    });

                    // Load cities if currentCity is set. TASK-Z6SK3T: no request for an
                    // empty region — the legacy endpoint returned [] for it anyway; the
                    // same end-state (select hidden, input shown) is applied locally.
                    let initialRegionId = $('#region_id').val();
                    this._loadCities(initialRegionId, this.options.currentCity);
                    clearInterval(regionInterval);
                }
            }, 500);
        },

        /**
         * Load cities based on regionId.
         * TASK-Z6SK3T: canonical addressLocations via the shared page-session cache
         * (was GetListCity — deprecated, uncached, one POST per region change).
         * @private
         * @param {String} regionId - The region ID to load cities for.
         * @param {String} [currentCity] - The current city to set as selected (optional).
         */
        _loadCities: function (regionId, currentCity) {
            let self = this;

            if (!regionId) {
                this._updateCityDropdown([], currentCity);
                return;
            }

            // Show loader
            $('body').loader('show');

            locationCache.getLocations($('#country').val() || '', regionId).then(function (cities) {
                self._updateCityDropdown(cities || [], currentCity);
            }).catch(function (error) {
                console.error('City request failed:', error);
            }).finally(function () {
                // Hide loader
                $('body').loader('hide');
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
                url: window.BASE_URL.replace(/index\.php\/?$/, '') + 'graphql',
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
                }

                citySelect.show();
                $('#city-input').hide(); // Assuming you have an input field for city selection
            } else {
                citySelect.hide();
                $('#city-input').show(); // Show input field if no cities are available
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
