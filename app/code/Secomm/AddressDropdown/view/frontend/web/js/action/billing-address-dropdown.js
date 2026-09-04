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
    const BILLING_ADDRESS_AREA = '.checkout-billing-address';
    const BILLING_ADDRESS_CITY = '.billing-address-city';
    const CITY_ERROR = '#checkout-step-billing #custom-city-error';
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
            $(BILLING_ADDRESS_AREA).find($('div[name="billingAddress.customCity"]')).hide();
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
                    self.initializeCityCascadeElements();
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

        initializeCityCascadeElements: function () {
            let self = this;
            let cityElement = $(CITY_SELECTOR);
            if (!cityElement.length) {
                let observer = new MutationObserver(function (mutations) {
                    mutations.forEach(function () {
                        cityElement = $(CITY_SELECTOR);
                        if (cityElement.length) {
                            self.setupCityCascade(cityElement);
                            observer.disconnect();
                        }
                    });
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            } else {
                self.setupCityCascade(cityElement);
            }
        },

        /* TASK-FMAN1B: Address Profile schema drives the ward label/placeholder
         * (DEC-FEAT2PZQKJ-001 — label from profile, not from structure). States:
         * null = fetch in flight / not started, false = unmapped country or failure
         * (i18n fallbacks then apply). */
        _vnSchema: null,
        _vnSchemaLoading: false,

        ensureVnSchema: function () {
            let self = this;
            if (this._vnSchema !== null || this._vnSchemaLoading) {
                return;
            }
            this._vnSchemaLoading = true;
            $.ajax({
                url: '/graphql',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    query: 'query{addressSchema(input:{country_id:"VN"})' +
                        '{profile_code levels{entity_type label placeholder}}}'
                }),
                success: function (response) {
                    self._vnSchemaLoading = false;
                    let schema = response && response.data && response.data.addressSchema;
                    if (!schema || !schema.profile_code) {
                        self._vnSchema = false;
                        return;
                    }
                    let levels = schema.levels || [];
                    let regionLevel = levels.find(function (level) { return level.entity_type === 'region'; });
                    let cityLevel = levels.find(function (level) { return level.entity_type === 'city'; });
                    self._vnSchema = {
                        profileCode: schema.profile_code,
                        regionLabel: regionLevel && regionLevel.label || '',
                        wardLabel: cityLevel && cityLevel.label || '',
                        wardPlaceholder: cityLevel && cityLevel.placeholder || ''
                    };
                    self.applySchemaToDom();
                },
                error: function () {
                    self._vnSchemaLoading = false;
                    self._vnSchema = false;
                }
            });
        },

        wardLabel: function () {
            return (this._vnSchema && this._vnSchema.wardLabel) || $.mage.__('Ward/Commune');
        },

        wardPlaceholder: function () {
            return (this._vnSchema && this._vnSchema.wardPlaceholder) || $.mage.__('Please select a city');
        },

        /* Re-apply schema labels when the fetch resolves after the DOM was built. */
        applySchemaToDom: function () {
            if (!this._vnSchema || !this.isVietnamCountry()) {
                return;
            }
            $('#checkout-step-billing label[for="custom-city-select"]').text(this.wardLabel());
            $(CUSTOM_CITY_SELECTOR).find('option:first').text(this.wardPlaceholder());
        },

        updatePostcodePlaceholderByDom: function (scopeSelector) {
            let self = this;
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
                        /* Schema label (i18n fallback). The literal EN string here used to
                         * overwrite the translated label on every region change. */
                        $cityLabel.text(self.wardLabel());
                    } else {
                        if (placeholder && placeholder.indexOf('*') === -1) {
                            $postcode.attr('placeholder', placeholder + '*');
                        }
                        $cityLabel.text($.mage.__('City'));
                    }

                    clearInterval(interval);
                }
            }, 200);
        },

        setupCityCascade: function (cityElement) {
            let self = this;
            this.ensureVnSchema();
            if ($('#custom-city-select-billing').length === 0) {
                let cityDiv = $('<div class="field mp-clear col-mp mp-6 required select _required shipping-address-city" name="billingAddress.customCity"></div>');
                let cityLabel = $('<label class="label" for="custom-city-select">' + this.wardLabel() + '</label>');
                let customCitySelect = $('<select required id="custom-city-select" name="custom_city" class="field input">')
                    .append($('<option></option>').attr('value', '').text(this.wardPlaceholder()));

                cityDiv.append(cityLabel).append(customCitySelect);

                let error = $('<div id="custom-city-error" class="field-error">\n' +
                    '                <span data-bind="text: element.error">' + $.mage.__('This is a required field.') + '</span>\n' +
                    '            </div>');

                /* Field order per profile cascade: Country -> Province/City -> Ward — anchor
                 * the ward select after the region field (the native city field sits next
                 * to country, which put the ward above the region on OSC's 2-column grid). */
                let regionField = $(REGION_SELECTOR).closest('.field');
                if (regionField.length) {
                    regionField.after(cityDiv);
                } else {
                    cityElement.closest('.field').after(cityDiv);
                }
                cityDiv.append(error);

                // Bind events and load initial data
                this.bindCityChange(customCitySelect);
                this.bindRegionChange(customCitySelect);
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
            });
        },

        bindRegionChange: function (customCitySelect) {
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
                        cityInput.val("").trigger('change');
                        customCitySelect.empty().append($('<option></option>').attr('value', '').text(self.wardPlaceholder()));

                        self.loadCities(selectedRegionId, function () {
                            let billingAddress = quote.billingAddress();
                            if (billingAddress) {
                                let cachedCity = billingAddress.city;
                                if (cachedCity) {
                                    customCitySelect.val(cachedCity).trigger('change');
                                }
                            }
                        });
                    });
                    clearInterval(regionElementInterval);
                }
            }, 500);
        },

        cityVisible: function () {
            if (!this.isVietnamCountry()) {
                $(BILLING_ADDRESS_AREA).find($('div[name="billingAddress.customCity"]')).hide();
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

        updateCityDropdown: function (cities) {
            let self = this;
            let customCitySelect = $(CUSTOM_CITY_SELECTOR);
            customCitySelect.empty().append($('<option></option>').attr('value', '').text(self.wardPlaceholder()));

            /* Target §8: Đ/đ sorts as D/d. ICU 'vi' keeps Đ a distinct letter after D
             * (verified 2026-08-28 via PHP intl), so normalise before comparing — same
             * rule as the server-side REPLACE expression in LocationHierarchyProvider. */
            let vnSortKey = function (value) {
                return String(value || '').replace(/Đ/g, 'D').replace(/đ/g, 'd');
            };
            cities.sort(function (a, b) {
                return vnSortKey(a.label).localeCompare(vnSortKey(b.label), 'vi', { sensitivity: 'base' });
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

        billingValidate: function () {
            var self = this;
            $(document).on("click", "#checkout-step-payment .action-update", function (event) {
                self.triggerValidCity();
                if ($(CITY_ERROR).is(':visible')) {
                    event.preventDefault();
                }
            })
        }
    });
});
