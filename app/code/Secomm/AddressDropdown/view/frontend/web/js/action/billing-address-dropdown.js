define([
    'jquery',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'mage/loader', // Ensure loader is included
    'knockout'
], function (
    $,
    Component,
    quote,
    loader,
    ko
) {
    'use strict';

    const CITY_SELECTOR = '#checkout-step-billing [name="city"]';
    const COUNTRY_SELECTOR = '#checkout-step-billing [name="country_id"]';
    const COUNTRY_SELECTOR_ALT = '#checkout-step-billing [name="billingAddress.country_id"]';
    const REGION_SELECTOR = '#checkout-step-billing [name="region_id"]';
    const CUSTOM_CITY_SELECTOR = '#checkout-step-billing [name="custom_city"]';
    const CUSTOM_SUB_CITY_SELECTOR = '#checkout-step-billing #custom-sub-city-select-billing';
    const BILLING_ADDRESS_AREA = '.checkout-billing-address';
    const BILLING_ADDRESS_SUB_CITY = '.billing-address-sub-city';
    const BILLING_ADDRESS_CITY = '.billing-address-city';
    const CUSTOM_ATTR_SUB_CITY = '#checkout-step-billing [name="custom_attributes[sub_city]"]';
    const CITY_ERROR = '#checkout-step-billing #custom-city-error';
    const SUB_CITY_ERROR = '#checkout-step-billing #custom-subcity-error';
    const CITY_DEFAULT = '#checkout-step-billing [name="billingAddress.city"]';
    return Component.extend({
        getCountryId: function () {
            let countryId = $(COUNTRY_SELECTOR).val()
                || $('#checkout-step-billing [name="billingAddress.country_id"]').val();
            let billingAddress = quote.billingAddress();

            if (!countryId && billingAddress && billingAddress.countryId) {
                countryId = billingAddress.countryId;
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
            $(BILLING_ADDRESS_AREA).find($('div[name="billingAddress.customCity"]')).hide();
            $('.billing-address-sub-city').hide();
            $(CITY_DEFAULT).removeClass('_error').show();
            $(CITY_DEFAULT).find(".field-error").hide();
        },

        initialize: function () {
            this._super();
            let self = this;
            this.lastCountryId = this.getCountryId();
            this.cityVisible();
            this.bindCountryChange();
            this.billingValidate();
            this.observeHashChange();
            quote.billingAddress.subscribe(function (address) {
                if (!address) {
                    return;
                }
                // country changed
                if (address.countryId) {
                    self.initializeCitySubCityElements();
                }
            });
        },

        observeHashChange: function () {
            let self = this;
            let hash = '';

            let findHash = setInterval(function () {
                if (window.location.hash !== '') {
                    hash = window.location.hash;
                    if (hash !== '#payment') {
                        clearInterval(findHash);
                        return;
                    }
                    self.bindCountryChange();
                    self.bindUpdateClick();
                    clearInterval(findHash);
                }
            }, 500);
        },

        bindUpdateClick: function () {
            let self = this;
            let updateButtonInterval = setInterval(function () {
                let updateButton = $('.action-update');
                if (updateButton.length) {
                    updateButton.on('click', function () {
                        self.triggerValidCity();
                    });
                    clearInterval(updateButtonInterval);
                }
            }, 500);
        },
        bindCountryChange: function () {
            let self = this;
            $(document)
                .off('change.secommBillingCountry', COUNTRY_SELECTOR + ', ' + COUNTRY_SELECTOR_ALT)
                .on('change.secommBillingCountry', COUNTRY_SELECTOR + ', ' + COUNTRY_SELECTOR_ALT, function () {
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
                self.setupCitySubCity(cityElement);
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
            if ($('#custom-city-select-billing').length === 0 && $(CUSTOM_SUB_CITY_SELECTOR).length === 0) {
                let subCityElement = $('<input type="hidden" name="billingAddress.sub_city" />');
                let cityDiv = $('<div class="field mp-clear col-mp mp-6 required select _required shipping-address-city" name="billingAddress.customCity"></div>');
                let cityLabel = $('<label class="label" for="custom-city-select">' + $.mage.__('City') + '</label>');
                let customCitySelect = $('<select required id="custom-city-select" name="custom_city" class="field input">')
                    .append($('<option></option>').attr('value', '').text($.mage.__('Please select a city')));

                cityDiv.append(cityLabel).append(customCitySelect);

                let subCityDiv = $('<div class="field _required billing-address-sub-city"></div>');
                let subCityLabel = $('<label class="label" for="custom-sub-city-select">' + $.mage.__('Sub-City') + '</label>');
                let customSubCitySelect = $('<select required id="custom-sub-city-select-billing" name="custom_sub_city" aria-required="true" class="field input">')
                    .append($('<option></option>').attr('value', '').text($.mage.__('Please select a sub-city')));
                let error = $('<div id="custom-city-error" class="field-error">\n' +
                    '                <span data-bind="text: element.error">' + $.mage.__('This is a required field.') + '</span>\n' +
                    '            </div>');
                let subCityError = $('<div id="custom-subcity-error" class="field-error">\n' +
                    '                <span data-bind="text: element.error">' + $.mage.__('This is a required field.') + '</span>\n' +
                    '            </div>');

                subCityDiv.append(subCityLabel).append(customSubCitySelect).append(subCityError);

                cityElement.closest('.field').after(cityDiv);
                cityDiv.after(subCityDiv);
                cityElement.after(subCityElement);
                cityDiv.append(error);

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
                        let billingAddress = quote.billingAddress();
                        if (billingAddress) {
                            let cachedCity = billingAddress.city;
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
                $(CITY_ERROR).hide();
                $(CUSTOM_CITY_SELECTOR).removeClass('custom-error');
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
                $(CUSTOM_ATTR_SUB_CITY).val(selectedSubCity).trigger('change');
                $(SUB_CITY_ERROR).hide();
                $(CUSTOM_SUB_CITY_SELECTOR).removeClass('custom-error');
            });
        },

        bindRegionChange: function (customCitySelect, customSubCitySelect) {
            let self = this;
            let regionElementInterval = setInterval(function () {
                let regionElement = $(REGION_SELECTOR);
                if (regionElement.length) {
                    regionElement.on('change', function () {
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
                        $(CUSTOM_ATTR_SUB_CITY).val("").trigger('change');
                        cityInput.val("").trigger('change');
                        customCitySelect.empty().append($('<option></option>').attr('value', '').text($.mage.__('Please select a city')));
                        customSubCitySelect.empty().append($('<option></option>').attr('value', '').text($.mage.__('Please select a sub-city')));

                        self.loadCities(selectedRegionId, function () {
                            let billingAddress = quote.billingAddress();
                            if (billingAddress) {
                                let cachedCity = billingAddress.city;
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
                    clearInterval(regionElementInterval);
                }
            }, 500);
        },

        cityVisible: function () {
            if (!this.isVietnamCountry()) {
                $(BILLING_ADDRESS_AREA).find($('div[name="billingAddress.customCity"]')).hide();
                $('.billing-address-sub-city').hide();
                $(CITY_DEFAULT).removeClass('_error').show();
                $(CITY_DEFAULT).find(".field-error").hide();

                return;
            }

            if ($(CUSTOM_CITY_SELECTOR).find('option').length <= 1) {
                $(BILLING_ADDRESS_AREA).find($('div[name="billingAddress.customCity"]')).hide();
                $(CITY_SELECTOR).val('').trigger('change');
                let cityInputInterval = setInterval(function () {
                    if ($(CITY_SELECTOR).length){
                        $(CITY_SELECTOR).val('');
                        clearInterval(cityInputInterval);
                    }
                }, 500);
                $(CITY_DEFAULT).removeClass('_error').show();
                $(CITY_DEFAULT).find(".field-error").hide();
            } else {
                $(BILLING_ADDRESS_AREA).find($('div[name="billingAddress.customCity"]')).show();
                $(CITY_DEFAULT).hide();
            }

            this.subCityVisible();
        },

        subCityVisible: function () {
            if (!this.isVietnamCountry()) {
                $('.billing-address-sub-city').hide();

                return;
            }

            if ($(CUSTOM_SUB_CITY_SELECTOR).find('option').length <= 1){
                $('.billing-address-sub-city').hide();
            }else{
                $('.billing-address-sub-city').show();

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
                        if (typeof callback === 'function') {
                            callback();
                        }
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
            let regionId = $(BILLING_ADDRESS_AREA).find($(REGION_SELECTOR)).val();
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
            customCitySelect.empty().append($('<option></option>').attr('value', '').text($.mage.__('Please select a city')));

            cities.sort(function (a, b) {
                return a.label.localeCompare(b.label, 'vi', { sensitivity: 'base' });
            });
            self.updatePostcodePlaceholderByDom('#checkout-step-billing');

            cities.forEach(function (city) {
                customCitySelect.append($('<option></option>').attr('value', city.default_name).text(city.label));
            });

            let cityInputViewModel = ko.dataFor($(CITY_SELECTOR)[0]);
            if (cityInputViewModel && cityInputViewModel.value) {
                let currentCity = cityInputViewModel.value();
                if (currentCity) {
                    customCitySelect.val(currentCity).trigger('change');
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
                customSubCitySelect.val(currentSubCity).trigger('change');
            } else {
                customSubCitySelect.val($(CUSTOM_ATTR_SUB_CITY).val()).trigger('change');
            }
        },
        triggerValidCity: function () {
            if ($(CUSTOM_CITY_SELECTOR).val() === '' && $(BILLING_ADDRESS_CITY).is(':visible')) {
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
            if ($(CUSTOM_SUB_CITY_SELECTOR).val() === '' && $(BILLING_ADDRESS_SUB_CITY).is(':visible')) {
                $(SUB_CITY_ERROR).show();
                $(CUSTOM_SUB_CITY_SELECTOR).addClass('custom-error');
            }else{
                $(SUB_CITY_ERROR).hide();
                $(CUSTOM_SUB_CITY_SELECTOR).removeClass('custom-error');
            }
        },

        billingValidate: function () {
            var self = this;
            $(document).on("click", "#checkout-step-payment .action-update", function (event) {
                self.triggerValidCity();
                self.triggerValidSubCity();
                if ($(CITY_ERROR).is(':visible') || $(SUB_CITY_ERROR).is(':visible')) {
                    event.preventDefault();
                }
            })
        }
    });
});
