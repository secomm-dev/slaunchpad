define([
    'jquery',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/set-shipping-information',
    'mage/loader', // Ensure loader is included
    'knockout'
], function (
    $,
    Component,
    quote,
    setShippingInformationAction,
    loader,
    ko
) {
    'use strict';

    const CITY_SELECTOR = '#co-shipping-form [name="city"]';
    const COUNTRY_SELECTOR = '#co-shipping-form [name="country_id"]';
    const COUNTRY_SELECTOR_ALT = '#co-shipping-form [name="shippingAddress.country_id"]';
    const REGION_SELECTOR = '#co-shipping-form [name="region_id"]';
    const CUSTOM_CITY_SELECTOR = '#co-shipping-form [name="custom_city"]';
    const CUSTOM_SUB_CITY_SELECTOR = '#co-shipping-form #custom-sub-city-select';
    const SHIPPING_ADDRESS_SUB_CITY = '.shipping-address-sub-city';
    const SHIPPING_ADDRESS_CITY = '.shipping-address-city';
    const CUSTOM_ATTR_SUB_CITY = '#co-shipping-form [name="custom_attributes[sub_city]"]';
    const CITY_ERROR = '#co-shipping-form #custom-city-error';
    const SUB_CITY_ERROR = '#co-shipping-form #custom-subcity-error';
    const CITY_DEFAULT = '#co-shipping-form [name="shippingAddress.city"]';
    return Component.extend({
        getCountryId: function () {
            let countryId = $(COUNTRY_SELECTOR).val()
                || $('#co-shipping-form [name="shippingAddress.country_id"]').val();
            let shippingAddress = quote.shippingAddress();

            if (!countryId && shippingAddress && shippingAddress.countryId) {
                countryId = shippingAddress.countryId;
            }

            return countryId;
        },

        isVietnamCountry: function (countryId) {
            return (countryId || this.getCountryId()) === 'VN';
        },

        applyNonVietnamUiState: function () {
            $(CUSTOM_CITY_SELECTOR).empty().append(
                $('<option></option>').attr('value', '').text($.mage.__('Please select a city'))
            );
            $(CUSTOM_SUB_CITY_SELECTOR).empty().append(
                $('<option></option>').attr('value', '').text($.mage.__('Please select a sub-city'))
            );
            $(CUSTOM_ATTR_SUB_CITY).val('').trigger('change');
            $('div[name="shippingAddress.customCity"]').hide();
            $(SHIPPING_ADDRESS_SUB_CITY).hide();
            $(CITY_DEFAULT).removeClass('_error').show();
            $(CITY_DEFAULT).find(".field-error").hide();
        },

        initialize: function () {
            this._super();
            let self = this;
            this.lastCountryId = this.getCountryId();
            this.cityVisible();
            this.bindCountryChange();

            this.observeHashChange();

            this.shippingValidate();

            let documentReadyInterval = setInterval(function () {
                let selectedRegionId = $(REGION_SELECTOR).val();
                if (selectedRegionId) {
                    self.initializeCitySubCityElements();
                    clearInterval(documentReadyInterval);
                }
            }, 500);
        },

        observeHashChange: function () {
            let self = this;
            let hash = '';

            let findHash = setInterval(function () {
                if (window.location.hash !== '') {
                    hash = window.location.hash;
                    if (hash !== '#shipping') {
                        return;
                    }
                    self.bindCountryChange();
                    clearInterval(findHash);
                }
            }, 500);
        },

        bindCountryChange: function () {
            let self = this;
            $(document)
                .off('change.secommShippingCountry', COUNTRY_SELECTOR + ', ' + COUNTRY_SELECTOR_ALT)
                .on('change.secommShippingCountry', COUNTRY_SELECTOR + ', ' + COUNTRY_SELECTOR_ALT, function () {
                let selectedCountryId = $(this).val();
                let previousCountryId = self.lastCountryId;

                if (previousCountryId === 'VN' && !self.isVietnamCountry(selectedCountryId)) {
                    let cityInputViewModel = ko.dataFor($(CITY_SELECTOR)[0]);
                    if (cityInputViewModel && cityInputViewModel.value) {
                        cityInputViewModel.value('');
                    }
                    $(CITY_SELECTOR).val('').trigger('change');
                }

                if (!self.isVietnamCountry(selectedCountryId)) {
                    self.applyNonVietnamUiState();
                }

                self.lastCountryId = selectedCountryId;
                self.cityVisible();
            });
        },

        initializeCitySubCityElements: function () {
            let self = this;
            let cityElement = $(CITY_SELECTOR);
            if (!cityElement.length) {
                let observer = new MutationObserver(function (mutations) {
                    mutations.forEach(function () {
                        cityElement = $(CITY_SELECTOR);
                        if (cityElement.length) {
                            self.setupCitySubCity(cityElement);
                            observer.disconnect();
                        }
                    });
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            } else {
                self.setupCitySubCity(cityElement); // Call setup directly if city element already exists
            }
        },

        updatePostcodePlaceholderByDom: function (scopeSelector) {
            let interval = setInterval(function () {
                let $scope = $(scopeSelector);

                if (!$scope.length) {
                    return;
                }
                let $postcode  = $scope.find('input[name="postcode"]');
                let $country   = $scope.find('select[name="country_id"]');
                let $cityLabel = $scope.find('label[for="custom-city-select"]');

                if ($postcode.length && $country.length) {
                    let placeholder = $postcode.attr('placeholder');
                    let countryId = $country.val();

                    if (countryId === 'VN') {
                        if (placeholder && placeholder.indexOf('*') !== -1) {
                            $postcode.attr('placeholder', placeholder.replace('*', '').trim());
                        }
                        $cityLabel.text('Ward/Commune');
                    } else {
                        if (placeholder && placeholder.indexOf('*') === -1) {
                            $postcode.attr('placeholder', placeholder + '*');
                        }
                        $cityLabel.text('City');
                    }

                    clearInterval(interval);
                }
            }, 200);
        },

        setupCitySubCity: function (cityElement) {
            let self = this;
            if ($(CUSTOM_CITY_SELECTOR).length === 0 && $(CUSTOM_SUB_CITY_SELECTOR).length === 0) {
                let subCityElement = $('<input type="hidden" name="shippingAddress.sub_city" />');
                let cityDiv = $('<div class="field mp-clear col-mp mp-6 required select _required shipping-address-city" name="shippingAddress.customCity"></div>');
                let cityLabel = $('<label class="label" for="custom-city-select">' + $.mage.__('Ward/Commune') + '</label>');
                let customCitySelect = $('<select required id="custom-city-select" name="custom_city" class="field input">')
                    .append($('<option></option>').attr('value', '').text($.mage.__('Please select a city')));
                let error = $('<div id="custom-city-error" class="field-error">\n' +
                    '                <span data-bind="text: element.error">' + $.mage.__('This is a required field.') + '</span>\n' +
                    '            </div>');
                cityDiv.append(cityLabel).append(customCitySelect);
                cityDiv.append(error);


                let subCityDiv = $('<div class="field col-mp mp-6 required select _required shipping-address-sub-city"></div>');
                let subCityLabel = $('<label class="label" for="custom-sub-city-select">' + $.mage.__('Sub-City') + '</label>');
                let customSubCitySelect = $('<select required id="custom-sub-city-select" name="custom_sub_city" aria-required="true" class="field input">')
                    .append($('<option></option>').attr('value', '').text($.mage.__('Please select a sub-city')));
                let subCityError = $('<div id="custom-subcity-error" class="field-error">\n' +
                    '                <span data-bind="text: element.error">' + $.mage.__('This is a required field.') + '</span>\n' +
                    '            </div>');
                subCityDiv.append(subCityLabel).append(customSubCitySelect);

                subCityDiv.append(subCityError);
                cityElement.closest('.field').after(cityDiv);
                cityDiv.after(subCityDiv);
                cityElement.after(subCityElement);

                // Bind events and load initial data
                this.bindCityChange(customCitySelect);
                this.bindSubCityChange(customSubCitySelect, subCityElement);
                this.bindRegionChange(customCitySelect, customSubCitySelect);
                this.cityVisible();

                if (!self.isVietnamCountry()) {
                    self.applyNonVietnamUiState();
                    return;
                }

                // Load initial cities based on selected region
                let selectedRegionId = $(REGION_SELECTOR).val(); // Assuming region selector exists
                if (selectedRegionId && self.isVietnamCountry()) {
                    self.loadCities(selectedRegionId, function () {
                        let shippingAddress = quote.shippingAddress();
                        if (shippingAddress) {
                            let cachedCity = shippingAddress.city;
                            if (cachedCity) {
                                customCitySelect.val(cachedCity).trigger('change');
                                self.loadSubCities(cachedCity, "");
                            }
                        }
                    });
                }
            }
        },


        bindCityChange: function (customCitySelect) {
            let self = this;

            customCitySelect.on('change', function () {
                let selectedCity = $(this).val() ?? "";
                let cityInputViewModel = ko.dataFor($(CITY_SELECTOR)[0]);
                if (cityInputViewModel && cityInputViewModel.value) {
                    cityInputViewModel.value(selectedCity);
                    $(CITY_SELECTOR).val(selectedCity).trigger('change');
                }
                let hash = '';
                hash = window.location.hash;
                if (hash === '#shipping') {
                    setShippingInformationAction();
                    $(CITY_ERROR).hide();
                    $(CUSTOM_CITY_SELECTOR).removeClass('custom-error');
                }
                if (self.isVietnamCountry()) {
                    self.loadSubCities(selectedCity, "");
                }
            });
        },

        bindSubCityChange: function (customSubCitySelect, subCityElement) {
            let self = this;
            customSubCitySelect.on('change', function () {
                let selectedSubCity = $(this).val();
                    subCityElement.val(selectedSubCity).trigger('change');
                    quote.shippingAddress().extension_attributes = { sub_city: selectedSubCity };
                    $(CUSTOM_ATTR_SUB_CITY).val(selectedSubCity).trigger('change');
                    setShippingInformationAction();
                $(SUB_CITY_ERROR).hide();
                $(CUSTOM_SUB_CITY_SELECTOR).removeClass('custom-error');
            });
        },

        bindRegionChange: function (customCitySelect, customSubCitySelect) {
            let self = this;
            $(document).on('change', REGION_SELECTOR, function () {
                let currentCountryId = self.getCountryId();
                if (self.lastCountryId === 'VN' && currentCountryId !== 'VN') {
                    let cityInputViewModel = ko.dataFor($(CITY_SELECTOR)[0]);
                    if (cityInputViewModel && cityInputViewModel.value) {
                        cityInputViewModel.value('');
                    }
                    $(CITY_SELECTOR).val('').trigger('change');
                }
                self.lastCountryId = currentCountryId;
                if (!self.isVietnamCountry()) {
                    self.applyNonVietnamUiState();
                    return;
                }
                let selectedRegionId = $(this).val();
                let cityInput = $(CITY_SELECTOR);
                $(CUSTOM_CITY_SELECTOR).val("").trigger('change');
                cityInput.val("").trigger('change');
                customCitySelect.empty().append($('<option></option>').attr('value', '').text($.mage.__('Please select a city')));
                customSubCitySelect.empty().append($('<option></option>').attr('value', '').text($.mage.__('Please select a sub-city')));

                self.loadCities(selectedRegionId, function () {
                    let shippingAddress = quote.shippingAddress();
                    if (shippingAddress) {
                        let cachedCity = shippingAddress.city;
                        if (cachedCity) {
                            customCitySelect.val(cachedCity).trigger('change');
                            self.loadSubCities(cachedCity, "");
                        }
                    }
                });

                if (cityInput.val() === "") {
                    $(CUSTOM_ATTR_SUB_CITY).val("").trigger('change');
                }
            });
        },

        cityVisible: function () {
            if (!this.isVietnamCountry()) {
                $('div[name="shippingAddress.customCity"]').hide();
                $(SHIPPING_ADDRESS_SUB_CITY).hide();
                $(CITY_DEFAULT).removeClass('_error').show();
                $(CITY_DEFAULT).find(".field-error").hide();

                return;
            }

            if ($(CUSTOM_CITY_SELECTOR).find('option').length <= 1) {
                $('div[name="shippingAddress.customCity"]').hide();
                $('[name="city"]').val('').trigger('change');
                $(CITY_DEFAULT).removeClass('_error').show();
                $(CITY_DEFAULT).find(".field-error").hide();
            } else {
                $('div[name="shippingAddress.customCity"]').show();
                $(CITY_DEFAULT).hide();
            }
            this.subCityVisible();
        },

        subCityVisible: function () {
            if (!this.isVietnamCountry()) {
                $(SHIPPING_ADDRESS_SUB_CITY).hide();

                return;
            }

            if ($(CUSTOM_SUB_CITY_SELECTOR).find('option').length <= 1 || $('#custom-city-select').val() == null){
                $(SHIPPING_ADDRESS_SUB_CITY).hide();
            }else{
                $(SHIPPING_ADDRESS_SUB_CITY).show();
            }
        },

        loadCities: function (regionId, callback) {
            let self = this;
            let query = `
                query {
                    GetListCity(input: { region_id: "${regionId}" }) {
                        default_name
                        label
                    }
                }
            `;

            $('body').loader('show');

            $.ajax({
                url: '/graphql',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ query: query }),
                success: function (response) {
                    if (!self.isVietnamCountry()) {
                        self.applyNonVietnamUiState();
                        return;
                    }
                    if (response.data && response.data.GetListCity && response.data.GetListCity.length > 0) {
                        self.updateCityDropdown(response.data.GetListCity);
                    } else {
                        self.updateCityDropdown([])
                    }
                    self.cityVisible();
                },
                error: function (xhr, status, error) {
                    console.error('Request failed:', error);
                },
                complete: function () {
                    $('body').loader('hide');
                }

            });
        },

        loadSubCities: function (cityId, currentSubCity) {
            let self = this;
            let regionId = $(REGION_SELECTOR).val();
            let query = `
                query {
                    GetListSubCity(input: { default_name: "${cityId}", region_id: "${regionId}" }) {
                        default_name
                        label
                    }
                }
            `;

            $('body').loader('show');

            $.ajax({
                url: '/graphql',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ query: query }),
                success: function (response) {
                    if (response.data && response.data.GetListSubCity) {
                        self.updateSubCityDropdown(response.data.GetListSubCity, currentSubCity);
                    } else {
                        self.updateSubCityDropdown([])
                    }
                    self.subCityVisible();
                },
                error: function (xhr, status, error) {
                    console.error('Request failed:', error);
                },
                complete: function () {
                    $('body').loader('hide');
                }
            });
        },

        updateCityDropdown: function (cities) {
            let self = this;
            let customCitySelect = $(CUSTOM_CITY_SELECTOR);
            cities.sort(function (a, b) {
                return a.label.localeCompare(b.label, 'vi', { sensitivity: 'base' });
            });
            self.updatePostcodePlaceholderByDom('#co-shipping-form');

            customCitySelect.empty().append($('<option selected></option>').attr('value', '').text($.mage.__('Please select a city')));

            cities.forEach(function (city) {
                customCitySelect.append($('<option></option>').attr('value', city.default_name).text(city.label));
            });

            let cityInputViewModel = ko.dataFor($(CITY_SELECTOR)[0]);
            if (cityInputViewModel && cityInputViewModel.value) {
                let currentCity = cityInputViewModel.value();
                if (currentCity && cities.find(element => element.default_name === currentCity)) {
                    customCitySelect.val(currentCity).trigger('change');
                } else {
                    customCitySelect.val("").trigger('change');
                }
            }
        },

        updateSubCityDropdown: function (subCities, currentSubCity) {
            let customSubCitySelect = $(CUSTOM_SUB_CITY_SELECTOR);
            customSubCitySelect.empty().append($('<option></option>').attr('value', '').text($.mage.__('Please select a sub-city')));

            subCities.forEach(function (subCity) {
                customSubCitySelect.append($('<option></option>').attr('value', subCity.default_name).text(subCity.label));
            });

            if (currentSubCity) {
                customSubCitySelect.val(currentSubCity).trigger("change");
            } else {
                customSubCitySelect.val($(CUSTOM_ATTR_SUB_CITY).val()).trigger("change");
            }
        },
        triggerValidCity: function () {
            if ($(CUSTOM_CITY_SELECTOR).val() === '' && $(SHIPPING_ADDRESS_CITY).is(':visible')) {
                $(CITY_ERROR).show();
                $(CUSTOM_CITY_SELECTOR).addClass('custom-error');
            }else{
                $(CITY_ERROR).hide();
                $(CUSTOM_CITY_SELECTOR).removeClass('custom-error');
            }
            if ($(CITY_SELECTOR).val() === '' && $(CITY_DEFAULT).is(':visible')){
                $(CITY_DEFAULT).find(".field-error").show();
            }
        },

        triggerValidSubCity: function () {
            if ($(CUSTOM_SUB_CITY_SELECTOR).val() === '' && $(SHIPPING_ADDRESS_SUB_CITY).is(':visible')) {
                $(SUB_CITY_ERROR).show();
                $(CUSTOM_SUB_CITY_SELECTOR).addClass('custom-error');
            }else{
                $(SUB_CITY_ERROR).hide();
                $(CUSTOM_SUB_CITY_SELECTOR).removeClass('custom-error');
            }
        },

        shippingValidate: function () {
            var self = this;
            $(document).on("click", "#shipping-method-buttons-container .continue, .new-shipping-address-modal .action-save-address", function (event) {
                self.triggerValidCity();
                self.triggerValidSubCity();
                if ($(CITY_ERROR).is(':visible') || $(SUB_CITY_ERROR).is(':visible')) {
                    event.preventDefault();
                }
            })
        }
    });
});
