define([
    'jquery',
    'mage/utils/wrapper',
    'mage/validation',
    'Secomm_AddressDropdown/js/form/schema-cascade'
], function ($, wrapper, schemaCascade) {
    'use strict';

    /*
     * Secomm_AddressDropdown — generic, country-agnostic, schema-driven multi-level
     * address cascade for the admin customer-address form (DEC-019/025).
     *
     * SL-011: this mixin owns ONLY the generic mechanism. It contains NO `country == 'VN'`
     * gate and NO VN-specific labels — VN behaviour lives in Secomm_VietNamAddress. For a
     * country whose Address Profile resolves to no city levels the cascade falls back to
     * Magento's native `city` text input (data-driven per DEC-025).
     *
     * TASK-9EX975 Slice A: the level count, labels and placeholders now come from the
     * resolved Address Profile (GraphQL `addressSchema`), the options per level from
     * `addressLocations` — through the shared `schema-cascade` factory. Deeper levels of
     * the recursive hierarchy render automatically (pre-2025 VN: province → district →
     * ward); the deepest selection persists into the native `city` input exactly as the
     * single-level cascade did.
     *
     * The form is loaded via AJAX into the customer_address_update_modal
     * (Magento_Customer insertForm), which destroys and re-renders the whole form on
     * every open — so each provider instance owns exactly one address form. Every
     * selector is therefore resolved against that form's root (the closest fieldset that
     * also holds region_id), never globally, and a per-root guard prevents the rare case
     * of a stale provider re-binding a newer form. The initial city load waits briefly
     * for region_id to be hydrated by the region UI component (it may populate after
     * city_select renders in the AJAX modal); Add-new (no region yet) relies on the
     * region change handler. The saved city name is read from the provider data so the
     * Edit pre-select is robust even if the native city input lags hydration.
     */

    return function (Provider) {
        return Provider.extend({
            initialize: function () {
                this._super();
                let self = this;

                let savedCity = (self.data && self.data.city) ? self.data.city : '';

                self.SELECTORS = {
                    COUNTRY_ID: '[name="country_id"]',
                    REGION_ID: '[name="region_id"]',
                    CITY_SELECT: 'select[name="city_select"]',
                    CITY_SELECT_CONTAINER: '[data-index="city_select"]',
                    CITY_INPUT: 'input[name="city"]',
                    CITY_INPUT_CONTAINER: '[data-index="city"]'
                };

                // Poll until this form's city_select renders, then wire that form's cascade.
                self._setupInterval = setInterval(function () {
                    let $citySelect = $(self.SELECTORS.CITY_SELECT);
                    if ($citySelect.length) {
                        clearInterval(self._setupInterval);
                        self._setupInterval = null;
                        self._setupCascade($citySelect.first(), savedCity);
                    }
                }, 500);

                return this;
            },

            /**
             * Clear the polling timers and the cascade when the AJAX form is torn down by
             * insertForm, so a destroyed provider never binds a later form. Best-effort —
             * teardown is non-fatal.
             */
            destroy: function () {
                try {
                    if (this._setupInterval) {
                        clearInterval(this._setupInterval);
                        this._setupInterval = null;
                    }
                    if (this._regionTimer) {
                        clearInterval(this._regionTimer);
                        this._regionTimer = null;
                    }
                    if (this._cascade) {
                        this._cascade.destroy();
                        this._cascade = null;
                    }
                } catch (error) {
                    // ignore — destroy must never throw
                }
                return this._super();
            },

            /**
             * The nearest ancestor of a field that also holds the region field is this
             * form's root. Falls back to <form>, then document.
             */
            _resolveRoot: function ($el) {
                let selectors = this.SELECTORS;
                let candidates = [$el.closest('fieldset'), $el.closest('form'), $(document)];

                for (let i = 0; i < candidates.length; i++) {
                    if (candidates[i].length && candidates[i].find(selectors.REGION_ID).length) {
                        return candidates[i];
                    }
                }
                return $(document);
            },

            /**
             * Bind the schema-driven cascade once the customer-address form is present.
             * Guarded per-root so a re-rendered/re-opened form is wired at most once.
             */
            _setupCascade: function ($citySelect, savedCity) {
                let self = this;
                let $root = self._resolveRoot($citySelect);

                if ($root.data('secommCascadeBound')) {
                    return;
                }
                $root.data('secommCascadeBound', true);

                self.fields = {
                    $root: $root,
                    $countryId: $root.find(self.SELECTORS.COUNTRY_ID).first(),
                    $regionId: $root.find(self.SELECTORS.REGION_ID).first(),
                    $citySelect: $root.find(self.SELECTORS.CITY_SELECT).first(),
                    $citySelectContainer: $root.find(self.SELECTORS.CITY_SELECT_CONTAINER).first(),
                    $cityInput: $root.find(self.SELECTORS.CITY_INPUT).first(),
                    $cityInputContainer: $root.find(self.SELECTORS.CITY_INPUT_CONTAINER).first()
                };

                let f = self.fields;

                // Seed old-value from the saved record so Edit can re-select it.
                let currentCity = savedCity || f.$cityInput.val();
                f.$citySelect.attr('old-value', currentCity);

                self._cascade = schemaCascade.createCascade({
                    level0Select: f.$citySelect,
                    injectAfter: f.$citySelect,
                    getCountryId: function () {
                        return f.$countryId.val() || '';
                    },
                    getRegionId: function () {
                        return f.$regionId.val() || '';
                    },
                    getSavedName: function () {
                        return currentCity;
                    },
                    onLeaf: function (leafName) {
                        f.$cityInput.val(leafName).change();
                    },
                    onSchemaResolved: function (hasSchema) {
                        f.$citySelectContainer.toggle(hasSchema);
                        f.$cityInputContainer.toggle(!hasSchema);
                        if (!hasSchema && !f.$citySelect.find('option').length) {
                            f.$citySelect.append($('<option></option>')
                                .attr('value', '-').text($.mage.__('Select a city')));
                            f.$citySelect.val('-').change();
                        }
                    },
                    fallbackPlaceholder: $.mage.__('Select a city')
                });

                // region change -> reload the cascade for the new region (schema-driven).
                f.$regionId.on('change', function () {
                    f.$cityInput.val('');
                    self._cascade.refresh();
                });

                // country change -> re-resolve the profile (level count may change).
                f.$countryId.on('change', function () {
                    f.$regionId.val('');
                    f.$cityInput.val('');
                    f.$citySelect.val('').change();
                    self._cascade.refresh();
                });

                // Initial load. region_id may be hydrated after city_select in the AJAX
                // modal, so wait briefly for it (Edit pre-select path); Add-new relies on
                // the region change handler above.
                f.$cityInput.val('');
                self._loadWhenReady(currentCity);
            },

            /**
             * Wait (bounded) for region_id to populate, then run the cascade once. The
             * region UI component sets the saved value during the AJAX render, not
             * necessarily before city_select appears, so a single read at setup time
             * would miss it.
             */
            _loadWhenReady: function (oldValueCity) {
                let self = this;
                let attempts = 0;
                let maxAttempts = 12; // ~3s at 250ms

                self._regionTimer = setInterval(function () {
                    let regionId = self.fields.$regionId.val();
                    if (regionId) {
                        clearInterval(self._regionTimer);
                        self._regionTimer = null;
                        self._cascade.refresh();
                    } else if (++attempts >= maxAttempts) {
                        clearInterval(self._regionTimer);
                        self._regionTimer = null;
                    }
                }, 250);
            }
        });
    };
});
