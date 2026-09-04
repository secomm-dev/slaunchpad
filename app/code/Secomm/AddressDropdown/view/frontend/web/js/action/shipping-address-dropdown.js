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
    const SHIPPING_ADDRESS_CITY = '.shipping-address-city';
    const CITY_ERROR = '#co-shipping-form #custom-city-error';
    const CITY_DEFAULT = '#co-shipping-form [name="shippingAddress.city"]';
    return Component.extend({
        isRegionChanging: false,

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
            $('div[name="shippingAddress.customCity"]').hide();
            $(CITY_DEFAULT).removeClass('_error').show();
            $(CITY_DEFAULT).find(".field-error").hide();
        },

        initialize: function () {
            this._super();
            let self = this;
            this.lastCountryId = this.getCountryId();
            this.ensureVnSchema();
            this.cityVisible();
            this.bindCountryChange();

            this.observeHashChange();

            this.shippingValidate();

            let setupAttempts = 0;
            let documentReadyInterval = setInterval(function () {
                /* Round 3: build the cascade as soon as the region FIELD exists — not
                 * only once it has a value — so the ward dropdown (schema placeholder)
                 * is visible before a region is picked. The initial loadCities in
                 * setupCityCascade already no-ops while the region is empty. */
                if ($(REGION_SELECTOR).length || ++setupAttempts > 120) {
                    if ($(REGION_SELECTOR).length) {
                        self.initializeCityCascadeElements();
                    }
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
                self.setupCityCascade(cityElement); // Call setup directly if city element already exists
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
            $('#co-shipping-form label[for="custom-city-select"]').text(this.wardLabel());
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
            if ($(CUSTOM_CITY_SELECTOR).length === 0) {
                let cityDiv = $('<div class="field mp-clear col-mp mp-6 required select _required shipping-address-city" name="shippingAddress.customCity"></div>');
                let cityLabel = $('<label class="label" for="custom-city-select">' + this.wardLabel() + '</label>');
                let customCitySelect = $('<select required id="custom-city-select" name="custom_city" class="field input">')
                    .append($('<option></option>').attr('value', '').text(this.wardPlaceholder()));
                let error = $('<div id="custom-city-error" class="field-error">\n' +
                    '                <span data-bind="text: element.error">' + $.mage.__('This is a required field.') + '</span>\n' +
                    '            </div>');
                cityDiv.append(cityLabel).append(customCitySelect);
                cityDiv.append(error);

                /* Field order per profile cascade: Country -> Province/City -> Ward — anchor
                 * the ward select after the region field (the native city field sits next
                 * to country, which put the ward above the region on OSC's 2-column grid). */
                let regionField = $(REGION_SELECTOR).closest('.field');
                if (regionField.length) {
                    regionField.after(cityDiv);
                } else {
                    cityElement.closest('.field').after(cityDiv);
                }

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
                        let shippingAddress = quote.shippingAddress();
                        if (shippingAddress && shippingAddress.city) {
                            let cachedCity = shippingAddress.city;
                            let matched = customCitySelect.find('option').filter(function () {
                                return $(this).text() === cachedCity || $(this).val() === cachedCity;
                            }).first();
                            if (matched.length) {
                                customCitySelect.val(matched.val());
                                // sync KO viewmodel with label of city
                                let cityInputViewModel = ko.dataFor($(CITY_SELECTOR)[0]);
                                if (cityInputViewModel && cityInputViewModel.value) {
                                    cityInputViewModel.value(matched.text());
                                    $(CITY_SELECTOR).val(matched.text()).trigger('change');
                                }
                            }
                        }
                    });
                }
            }
        },


        bindCityChange: function (customCitySelect) {
            let self = this;

            customCitySelect.on('change', function () {
                let selectedOption = $(this).find('option:selected');
                let cityLabel = selectedOption.text() || ($(this).val() ?? "");
                let cityInputViewModel = ko.dataFor($(CITY_SELECTOR)[0]);
                if (cityInputViewModel && cityInputViewModel.value) {
                    cityInputViewModel.value(cityLabel);
                    $(CITY_SELECTOR).val(cityLabel).trigger('change');
                }
                let hash = window.location.hash;
                if (hash === '#shipping') {
                    setShippingInformationAction();
                    $(CITY_ERROR).hide();
                    $(CUSTOM_CITY_SELECTOR).removeClass('custom-error');
                }
            });
        },

        bindRegionChange: function (customCitySelect) {
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
                // Mark this as changing region so that CityDropdown update does not auto-restore the old city
                self.isRegionChanging = true;

                // Clear city DOM and KO viewmodel
                let cityInputViewModel = ko.dataFor($(CITY_SELECTOR)[0]);
                if (cityInputViewModel && cityInputViewModel.value) {
                    cityInputViewModel.value('');
                }
                $(CUSTOM_CITY_SELECTOR).val('');
                cityInput.val('').trigger('change');
                customCitySelect.empty().append($('<option></option>').attr('value', '').text(self.wardPlaceholder()));

                self.loadCities(selectedRegionId, null);

            });
        },

        cityVisible: function () {
            if (!this.isVietnamCountry()) {
                $('div[name="shippingAddress.customCity"]').hide();
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
                        self.updateCityDropdown(response.data.GetListCity, typeof callback === 'function');
                    } else {
                        self.updateCityDropdown([], false);
                    }
                    self.cityVisible();
                    if (typeof callback === 'function') {
                        callback();
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Request failed:', error);
                },
                complete: function () {
                    $('body').loader('hide');
                }

            });
        },

        updateCityDropdown: function (cities, suppressChange) {
            let self = this;
            let customCitySelect = $(CUSTOM_CITY_SELECTOR);
            /* Target §8: Đ/đ sorts as D/d. ICU 'vi' keeps Đ a distinct letter after D
             * (verified 2026-08-28 via PHP intl), so normalise before comparing — same
             * rule as the server-side REPLACE expression in LocationHierarchyProvider. */
            let vnSortKey = function (value) {
                return String(value || '').replace(/Đ/g, 'D').replace(/đ/g, 'd');
            };
            cities.sort(function (a, b) {
                return vnSortKey(a.label).localeCompare(vnSortKey(b.label), 'vi', { sensitivity: 'base' });
            });
            self.updatePostcodePlaceholderByDom('#co-shipping-form');

            customCitySelect.empty().append($('<option selected></option>').attr('value', '').text(self.wardPlaceholder()));

            cities.forEach(function (city) {
                customCitySelect.append(
                    $('<option></option>')
                        .attr('value', city.default_name)
                        .attr('data-label', city.label)
                        .text(city.label)
                );
            });
            // If changing regions, do not auto-restore the old city; reset the flag and stop.
            if (self.isRegionChanging) {
                self.isRegionChanging = false;
                customCitySelect.val('');
                return;
            }
            // Flow init: Synchronize the dropdown with the KO viewmodel (if a city has been saved)
            // suppressChange = true when there is a callback for further processing
            // suppressChange = false when there is no callback (no restore)
            let cityInputViewModel = ko.dataFor($(CITY_SELECTOR)[0]);
            if (cityInputViewModel && cityInputViewModel.value) {
                let currentCity = cityInputViewModel.value();
                let matched = cities.find(function (c) {
                    return c.label === currentCity || c.default_name === currentCity;
                });
                if (currentCity && matched) {
                    // Only set the value in the dropdown, DO NOT trigger 'change'
                    // KO sync will be handled by the callback in setupCityCascade
                    customCitySelect.val(matched.default_name);
                } else {
                    customCitySelect.val('');
                }
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


        shippingValidate: function () {
            var self = this;
            $(document).on("click", "#shipping-method-buttons-container .continue, .new-shipping-address-modal .action-save-address", function (event) {
                self.triggerValidCity();
                if ($(CITY_ERROR).is(':visible')) {
                    event.preventDefault();
                }
            })
        }
    });
});
