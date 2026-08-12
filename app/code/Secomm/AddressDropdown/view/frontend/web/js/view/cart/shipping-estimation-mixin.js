define( [
        'jquery',
        'Magento_Ui/js/form/form',
        'Magento_Checkout/js/action/select-shipping-address',
        'Magento_Checkout/js/model/address-converter',
        'Magento_Checkout/js/model/cart/estimate-service',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/shipping-rates-validator',
        'Magento_Checkout/js/model/quote',
        'mage/validation'
    ],
    function (
        $,
        Component,
        selectShippingAddress,
        addressConverter,
        estimateService,
        checkoutData,
        shippingRatesValidator,
        quote,
    ) {
    'use strict';

    var mixin = {
        COUNTRY_SELECTOR: '[name="country_id"]',
        REGION_SELECTOR: '[name="region_id"]',

        CITY_SELECTOR: '[name="custom_attributes[city]"]',
        CUSTOM_CITY_SELECTOR: '[name="custom_attributes[custom_city]"]',

        SUB_CITY_SELECTOR: '[name="custom_attributes[sub_city]"]',
        CUSTOM_SUB_CITY_SELECTOR: '[name="custom_attributes[custom_sub_city]"]',

        CITY_CONTAINER: '[name="shippingAddress.custom_attributes.city"]',
        CUSTOM_CITY_CONTAINER: '[name="shippingAddress.custom_attributes.custom_city"]',

        SUB_CITY_CONTAINER: '[name="shippingAddress.custom_attributes.sub_city"]',
        CUSTOM_SUB_CITY_CONTAINER: '[name="shippingAddress.custom_attributes.custom_sub_city"]',

        FIRST_LOAD_REGION: true,
        FIRST_LOAD_CITY: true,
        FIRST_LOAD_SUB_CITY: true,

        /**
         * @override
         */
        initElement: function (element) {
            this._super();

            if (element.index === 'address-fieldsets') {
                shippingRatesValidator.bindChangeHandlers(element.elems(), true, 500);
                element.elems.subscribe(function (elems) {
                    shippingRatesValidator.doElementBinding(elems[elems.length - 1], true, 500);
                });
            }
            this.initData();
            return this;
        },

        initData: function () {
            let self = this;
            self.countryChange();
            self.regionChange();
            self.cityChange();
            self.subCityChange();
        },

        countryChange: function () {
            let self = this;
            $(document).on('change', self.COUNTRY_SELECTOR, function () {
                self.setFieldStatus('city', false)
                self.setFieldStatus('custom_city', false)
                self.setFieldStatus('sub_city', false)
                self.setFieldStatus('custom_sub_city', false)
            })
        },

        regionChange: function () {
            let self = this;
            $(document).on('change', self.REGION_SELECTOR, function () {
                const address = quote.isVirtual() ? quote.billingAddress() : quote.shippingAddress();
                let selectedRegionId = self.FIRST_LOAD_REGION ? address.regionId : $(this).val() ;
                self.FIRST_LOAD_REGION = false;
                if (!selectedRegionId || selectedRegionId === '') {
                    self.setFieldStatus('city', false);
                    self.setFieldStatus('custom_city', false)
                    self.setFieldStatus('sub_city', false)
                    self.setFieldStatus('custom_sub_city', false)
                    return;
                }
                self.setFieldStatus('sub_city', false);
                self.setFieldStatus('custom_sub_city', false);
              self.loadCities(selectedRegionId);
            })
        },

        cityChange: function () {
            let self = this;
            $(document).on('change', self.CUSTOM_CITY_SELECTOR, function () {
                const address = quote.isVirtual() ? quote.billingAddress() : quote.shippingAddress();
                let first_value = self.getAttributeCodeValue(address?.customAttributes, 'custom_city');
                let selectedCity = self.FIRST_LOAD_CITY ? first_value : $(this).val();
                if (self.FIRST_LOAD_CITY) {
                    const setCityValue = setInterval(function () {
                        if ($(self.CUSTOM_CITY_SELECTOR).find("option").length > 1) {
                            self.FIRST_LOAD_CITY = false;
                            clearInterval(setCityValue);
                            $(self.CUSTOM_CITY_SELECTOR).val(selectedCity).trigger('change')
                        }
                    }, 500)
                }

                if (selectedCity === '') {
                    self.setFieldStatus('sub_city', false);
                    self.setFieldStatus('custom_sub_city', false);
                    return
                }
                let addressData = self.source.get('shippingAddress');
                addressConverter.formAddressDataToQuoteAddress(addressData);
                self.loadSubCities(selectedCity, "");
            })
        },

        subCityChange: function () {
            let self = this;
            $(document).on('change', self.CUSTOM_SUB_CITY_SELECTOR, function () {
                const address = quote.isVirtual() ? quote.billingAddress() : quote.shippingAddress();
                let first_value
                first_value = self.getAttributeCodeValue(address?.customAttributes, 'custom_sub_city');
                if (self.getAttributeCodeValue(address?.customAttributes, 'custom_city') === '') {
                    first_value = '';
                }
                let selectedSubCity = self.FIRST_LOAD_SUB_CITY ? first_value : $(this).val();
                if (self.FIRST_LOAD_SUB_CITY && selectedSubCity !== '') {
                    const setSubCityValue = setInterval(function () {
                        if ($(self.CUSTOM_SUB_CITY_SELECTOR).find("option").length > 1) {
                            self.FIRST_LOAD_SUB_CITY = false;
                            clearInterval(setSubCityValue);
                            $(self.CUSTOM_SUB_CITY_SELECTOR).val(selectedSubCity).trigger('change')
                        }
                    }, 2000)
                }

                if (selectedSubCity === '') return;
                let addressData = self.source.get('shippingAddress');
                addressConverter.formAddressDataToQuoteAddress(addressData);
            })
        },

        loadCities: function (regionId) {
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

        updateCityDropdown: function (cities) {
            let customCitySelect = $(this.CUSTOM_CITY_SELECTOR);
            customCitySelect.empty().append($('<option></option>').attr('value', '').text($.mage.__('Please select a city')));

            // Sort cities alphabetically (A-Z) using the Vietnamese locale so that
            if (cities && cities.length) {
                cities.sort(function (a, b) {
                    return (a.label || '').localeCompare((b.label || ''), 'vi', { sensitivity: 'base' });
                });
            }

            cities?.forEach(function (city) {
                customCitySelect.append($('<option></option>').attr('value', city.default_name).text(city.label));
            });
        },

        loadSubCities: function (cityId, currentSubCity) {
            let self = this;
            let regionId = $(mixin.REGION_SELECTOR).val();
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
                    if (response.data && response.data.GetListSubCity && response.data.GetListSubCity.length > 0) {
                        self.updateSubCityDropdown(response.data.GetListSubCity, currentSubCity);
                    } else {
                        self.updateSubCityDropdown([]);
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

        updateSubCityDropdown: function (subCities, currentSubCity) {
            let customSubCitySelect = $(this.CUSTOM_SUB_CITY_SELECTOR);
            customSubCitySelect.empty().append($('<option></option>').attr('value', '').text($.mage.__('Please select a sub-city')));

            // Sort sub-cities alphabetically (A-Z) using the Vietnamese locale so
            // that diacritics are ordered correctly before rendering the options.
            if (subCities && subCities.length) {
                subCities.sort(function (a, b) {
                    return (a.label || '').localeCompare((b.label || ''), 'vi', { sensitivity: 'base' });
                });
            }

            subCities.forEach(function (subCity) {
                customSubCitySelect.append($('<option></option>').attr('value', subCity.default_name).text(subCity.label));
            });

            if (currentSubCity) {
                customSubCitySelect.val(currentSubCity);
            }
        },

        cityVisible: function () {
            const option_length = $(this.CUSTOM_CITY_SELECTOR).find('option').length;
            if ( option_length <= 1) {
                $('[name="city"]').val('').trigger('change');
                this.setFieldStatus('city', false)
                this.setFieldStatus('custom_city', false)
            } else {
                this.setFieldStatus('city', false)
                this.setFieldStatus('custom_city', true)
            }
        },

        subCityVisible: function () {
            const option_length = $(this.CUSTOM_SUB_CITY_SELECTOR).find('option').length;
            const city_value = $(this.CUSTOM_SUB_CITY_SELECTOR).val();
            if (option_length <= 1 || city_value == null) {
                this.setFieldStatus('sub_city', false)
                this.setFieldStatus('custom_sub_city', false)
            } else {
                this.setFieldStatus('sub_city', false)
                this.setFieldStatus('custom_sub_city', true)
            }
        },

        setFieldStatus: function (field, status) {
            switch (field) {
                case 'city': status ? $(this.CITY_CONTAINER).show() : $(this.CITY_CONTAINER).hide(); break;
                case 'custom_city': status ? $(this.CUSTOM_CITY_CONTAINER).show() : $(this.CUSTOM_CITY_CONTAINER).hide(); break;
                case 'sub_city': status ? $(this.SUB_CITY_CONTAINER).show() : $(this.SUB_CITY_CONTAINER).hide(); break;
                case 'custom_sub_city': status ? $(this.CUSTOM_SUB_CITY_CONTAINER).show() : $(this.CUSTOM_SUB_CITY_CONTAINER).hide(); break;
                default: break;
            }
        },

        getAttributeCodeValue: function (attributeCode, code) {
            let result = '';
            attributeCode.map((attribute) => {
                if (attribute.attribute_code === code) {
                    result = attribute.value
                }
            })
            return result;
        }
    };

    return function (target) {
        return target.extend(mixin);
    };
});
