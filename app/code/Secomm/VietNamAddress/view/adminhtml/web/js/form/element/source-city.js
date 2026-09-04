define([
    'jquery',
    'Magento_Ui/js/form/element/abstract',
    'Secomm_AddressDropdown/js/form/schema-cascade'
], function ($, Abstract, schemaCascade) {
    'use strict';

    /*
     * MSI (Inventory) source form — city field.
     *
     * TASK-9EX975 Slice A: the cascade is SCHEMA-DRIVEN through the shared
     * Secomm_AddressDropdown factory. The level count, labels and placeholders come from
     * the source country's resolved Address Profile (GraphQL `addressSchema` via
     * `loadCityLevels`), each level's options from `addressLocations` (via
     * `loadLevelOptions`), with the storefront's stop-at-leaf semantics. There is no
     * `country == 'VN'` gate any more: a mapped country renders its profile's levels
     * (VN 2025 = ward, VN pre-2025 = district + ward), an unmapped country keeps
     * Magento's native City input. The deepest selection persists in the same
     * observable (`city` field of the source — DEC-FEATE2HM1J-001: each surface keeps
     * its native store).
     *
     * Behaviour kept from the single-level driver: a stale stored city name that does
     * not match any level-1 option is dropped (this surface has no free-text fallback);
     * clearing on a real country/region change but not on the initial hydration
     * (undefined -> value), so Edit pre-fill survives the KO imports.
     */

    return Abstract.extend({
        defaults: {
            elementTmpl: 'Secomm_VietNamAddress/form/element/source-city',
            cityLevels: [],
            hasSchema: false,
            imports: {
                countryChanged: '${ $.parentName }.country_id:value',
                regionChanged: '${ $.parentName }.region_id:value'
            }
        },

        initObservable: function () {
            this._super()
                .observe(['cityLevels', 'hasSchema']);

            this._schemaToken = 0;
            this._levelTokens = [];

            return this;
        },

        countryChanged: function (countryId) {
            var previousCountry = this.countryId;

            this.countryId = String(countryId || '').toUpperCase();

            if (previousCountry !== undefined && previousCountry !== this.countryId) {
                this.value('');
            }

            this.refreshCascade();
        },

        regionChanged: function (regionId) {
            var previousRegion = this.regionId;

            this.regionId = String(regionId || '');

            if (previousRegion !== undefined && previousRegion !== this.regionId && this.hasSchema()) {
                this.value('');
            }

            this.refreshCascade();
        },

        /**
         * Re-resolve the profile and rebuild the level views. A bumping token drops
         * every in-flight request of a previous (country|region) pair.
         */
        refreshCascade: function () {
            var self = this;
            var token = ++this._schemaToken;

            this._levelTokens = [];

            schemaCascade.loadCityLevels(this.countryId).then(function (schema) {
                if (token !== self._schemaToken) {
                    return;
                }

                self.hasSchema(!!(schema && schema.levels.length));
                self._profileCode = schema ? schema.profileCode : null;
                self._levels = schema ? schema.levels : [];
                self.renderLevels();

                if (schema) {
                    self.loadLevel(0);
                }
            });
        },

        /**
         * One KO view-model per schema level: its own options/selection/loading state,
         * rendered by the template's foreach.
         */
        renderLevels: function () {
            var levels = (this._levels || []).map(function (level, index) {
                return {
                    index: index,
                    label: level.label || '',
                    placeholder: level.placeholder || '',
                    options: ko.observableArray([]),
                    selected: ko.observable(''),
                    loading: ko.observable(false)
                };
            });

            this.cityLevels(levels);
        },

        /**
         * Load one level's options. Level 0 roots on region_id; deeper levels on the
         * parent city_id selected one level up. Empty children = stop-at-leaf: the
         * level hides and the parent selection stays the leaf value.
         */
        loadLevel: function (index) {
            var self = this;
            var level = this.cityLevels()[index];

            if (!level || !this._profileCode) {
                return;
            }

            var token = (this._levelTokens[index] || 0) + 1;
            this._levelTokens[index] = token;
            level.loading(true);

            if (index === 0 && !this.regionId) {
                level.options([]);
                level.selected('');
                level.loading(false);
                return;
            }

            var selector = index === 0
                ? {regionId: this.regionId}
                : {parentCityId: this.selectedId(index - 1)};

            schemaCascade.loadLevelOptions(this._profileCode, selector).then(function (nodes) {
                if (token !== self._levelTokens[index]) {
                    return;
                }

                var current = self.cityLevels()[index];
                var mapped = (nodes || []).filter(function (node) {
                    return node.default_name;
                }).map(function (node) {
                    return {
                        value: node.default_name,
                        label: node.name || node.default_name,
                        id: node.city_id
                    };
                });

                if (!mapped.length) {
                    current.options([]);
                    current.selected('');
                    current.loading(false);
                    return;
                }

                // Prefill matches the saved leaf name at the FIRST level only — the same
                // documented limitation as the other surfaces (the location path is not
                // persisted on native stores; TASK-YQSS3M owns the path contract).
                var saved = index === 0 ? (self.value() || '') : '';
                var matched = saved && mapped.some(function (option) {
                    return option.value === saved;
                });

                current.options(mapped);
                current.selected(matched ? saved : '');
                current.loading(false);

                if (index === 0 && saved && !matched) {
                    self.value('');
                }

                if (matched) {
                    self.loadLevel(index + 1);
                }
            });
        },

        /**
         * city_id of the option currently selected at `index` (parent of index + 1).
         */
        selectedId: function (index) {
            var level = this.cityLevels()[index];
            var name = level ? level.selected() : '';
            var option = null;

            (level ? level.options() : []).forEach(function (item) {
                if (item.value === name) {
                    option = item;
                }
            });

            return option ? option.id : null;
        },

        /**
         * A level select changed: the deepest current selection becomes the value,
         * deeper levels reset (and their in-flight requests dropped), and the next
         * level loads under the newly selected parent.
         */
        onLevelChange: function (index) {
            var levels = this.cityLevels();
            var idx;

            for (idx = index + 1; idx < levels.length; idx++) {
                this._levelTokens[idx] = (this._levelTokens[idx] || 0) + 1;
                levels[idx].options([]);
                levels[idx].selected('');
            }

            this.value(levels[index].selected() || '');

            if (levels[index].selected() && index + 1 < levels.length) {
                this.loadLevel(index + 1);
            }
        }
    });
});
