define([
    'jquery',
    'Magento_Ui/js/form/element/abstract'
], function ($, Abstract) {
    'use strict';

    function escapeGraphQlValue(value) {
        return JSON.stringify(String(value || ''));
    }

    return Abstract.extend({
        defaults: {
            elementTmpl: 'Secomm_VietNamAddress/form/element/source-city',
            cityOptions: [],
            cityCaption: '',
            isVietnam: false,
            isLoading: false,
            imports: {
                countryChanged: '${ $.parentName }.country_id:value',
                regionChanged: '${ $.parentName }.region_id:value'
            }
        },

        initObservable: function () {
            this._super()
                .observe(['cityOptions', 'isVietnam', 'isLoading']);

            this.cityCaption = $.mage.__('Please select a city');

            return this;
        },

        countryChanged: function (countryId) {
            var previousCountry = this.countryId;

            this.countryId = String(countryId || '').toUpperCase();
            this.isVietnam(this.countryId === 'VN');

            if (previousCountry !== undefined && previousCountry !== this.countryId) {
                this.value('');
            }

            this.refreshOptions();
        },

        regionChanged: function (regionId) {
            var previousRegion = this.regionId;

            this.regionId = String(regionId || '');

            if (previousRegion !== undefined && previousRegion !== this.regionId && this.isVietnam()) {
                this.value('');
            }

            this.refreshOptions();
        },

        refreshOptions: function () {
            var currentCity = this.value() || '';

            if (!this.isVietnam() || !this.regionId) {
                this.cityOptions([]);
                this.isLoading(false);
                return;
            }

            this.loadCities(this.regionId, currentCity);
        },

        loadCities: function (regionId, selectedCity) {
            var requestId = (this.cityRequestId || 0) + 1;
            var query = 'query { GetListCity(input: { region_id: ' +
                escapeGraphQlValue(regionId) +
                ', area: "adminhtml" }) { default_name label } }';

            this.cityRequestId = requestId;
            this.isLoading(true);

            fetch('/graphql', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({query: query})
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Unable to load city options.');
                    }

                    return response.json();
                })
                .then(function (payload) {
                    var cities;
                    var options;

                    if (this.cityRequestId !== requestId || !this.isVietnam() || this.regionId !== regionId) {
                        return;
                    }

                    if (payload && payload.errors) {
                        throw new Error('Unable to load city options.');
                    }

                    cities = (payload && payload.data && payload.data.GetListCity) ? payload.data.GetListCity : [];
                    options = cities.map(function (city) {
                        return {
                            value: city.default_name,
                            label: city.label || city.default_name
                        };
                    });

                    this.cityOptions(options);
                    if (selectedCity && !options.some(function (option) {
                        return option.value === selectedCity;
                    })) {
                        this.value('');
                    }
                }.bind(this))
                .catch(function () {
                    if (this.cityRequestId === requestId) {
                        this.cityOptions([]);
                    }
                }.bind(this))
                .finally(function () {
                    if (this.cityRequestId === requestId) {
                        this.isLoading(false);
                    }
                }.bind(this));
        }
    });
});
