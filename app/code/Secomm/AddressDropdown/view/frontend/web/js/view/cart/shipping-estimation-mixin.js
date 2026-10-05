define( [
        'jquery',
        'Magento_Ui/js/form/form',
        'Magento_Checkout/js/action/select-shipping-address',
        'Magento_Checkout/js/model/address-converter',
        'Magento_Checkout/js/model/cart/estimate-service',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/shipping-rates-validator',
        'Magento_Checkout/js/model/quote',
        'mage/validation',
        'Secomm_AddressDropdown/js/model/address-location-cache'
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
        validation,
        locationCache
    ) {
    'use strict';

    var mixin = {
        COUNTRY_SELECTOR: '[name="country_id"]',
        REGION_SELECTOR: '[name="region_id"]',

        CITY_SELECTOR: '[name="custom_attributes[city]"]',
        CUSTOM_CITY_SELECTOR: '[name="custom_attributes[custom_city]"]',

        CITY_CONTAINER: '[name="shippingAddress.custom_attributes.city"]',
        CUSTOM_CITY_CONTAINER: '[name="shippingAddress.custom_attributes.custom_city"]',

        FIRST_LOAD_REGION: true,
        FIRST_LOAD_CITY: true,

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
        },

        countryChange: function () {
            let self = this;
            $(document).on('change', self.COUNTRY_SELECTOR, function () {
                self.setFieldStatus('city', false)
                self.setFieldStatus('custom_city', false)
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
                    return;
                }
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

                let addressData = self.source.get('shippingAddress');
                addressConverter.formAddressDataToQuoteAddress(addressData);
            })
        },

        loadCities: function (regionId) {
            let self = this;

            $('body').loader('show');

            /* TASK-Z6SK3T: canonical addressLocations via the shared page-session cache
             * (was GetListCity — deprecated, uncached, one POST per region change). */
            locationCache.getLocations('VN', regionId).then(function (cities) {
                cities = cities || [];
                self.updateCityDropdown(cities);
                self.cityVisible();
            }, function (error) {
                console.error('Request failed:', error);
            }).finally(function () {
                $('body').loader('hide');
            });
        },

        updateCityDropdown: function (cities) {
            let customCitySelect = $(this.CUSTOM_CITY_SELECTOR);
            customCitySelect.empty().append($('<option></option>').attr('value', '').text($.mage.__('Please select a city')));

            cities?.forEach(function (city) {
                customCitySelect.append($('<option></option>').attr('value', city.default_name).text(city.label));
            });
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

        setFieldStatus: function (field, status) {
            switch (field) {
                case 'city': status ? $(this.CITY_CONTAINER).show() : $(this.CITY_CONTAINER).hide(); break;
                case 'custom_city': status ? $(this.CUSTOM_CITY_CONTAINER).show() : $(this.CUSTOM_CITY_CONTAINER).hide(); break;
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
