define(['jquery'], function ($) {
    'use strict';

    /*
     * TASK-9EX975 Slice A — generic schema-driven city cascade for the admin address
     * surfaces (customer address form, order create/edit, MSI source, config Store
     * Information / Shipping Origin).
     *
     * The NUMBER of city levels, their labels, placeholders and required flags come from
     * the resolved Address Profile via GraphQL `addressSchema`; each level's options from
     * `addressLocations` (region_id roots / parent_city_id children) — the same two
     * queries and the same stop-at-leaf semantics as the storefront schema renderer
     * (TASK-3T3NSV). Zero country-conditioned logic lives here (DEC-FEATJSZQV3-003): a
     * country whose profile resolves to no city levels simply falls back to the surface's
     * native city input, reported through `onSchemaResolved(false)`.
     *
     * The cascade manages N selects: level 0 IS the surface's existing select (required
     * rules, layout and styling keep working), deeper levels are injected after it. The
     * deepest selection is written through `onLeaf(name)` — every surface persists into
     * its own native store (DEC-FEATE2HM1J-001); this module never posts anything.
     *
     * Prefill matches the saved leaf name at the FIRST level only — the same documented
     * limitation as the storefront renderer (the location path is not persisted on
     * native stores; TASK-YQSS3M owns the path contract). Deeper levels load their
     * options but need a re-selection below a matched level-1 node.
     */

    var schemaMemo = {};

    /**
     * Run one GraphQL query against the storefront endpoint (public, cacheable resolvers).
     *
     * @param {String} query
     * @returns {Promise<Object|null>} data object or null on any failure
     */
    function fetchGraphQL(query) {
        return fetch('/graphql', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                query: query
            })
        }).then(function (response) {
            return response.ok ? response.json() : null;
        }).then(function (payload) {
            if (!payload || payload.errors) {
                return null;
            }

            return payload.data || null;
        }).catch(function () {
            return null;
        });
    }

    /**
     * City levels of the country's resolved Address Profile (memoized per page).
     *
     * @param {String} countryCode
     * @returns {Promise<{profileCode: String, levels: Array}|null>} null = native input
     */
    function loadCityLevels(countryCode) {
        var key = String(countryCode || '').toUpperCase();

        if (!key) {
            return Promise.resolve(null);
        }

        if (Object.prototype.hasOwnProperty.call(schemaMemo, key)) {
            return schemaMemo[key];
        }

        schemaMemo[key] = fetchGraphQL(
            'query{addressSchema(input:{country_id:' + JSON.stringify(key) + '})' +
            '{profile_code levels{entity_type depth label placeholder required}}}'
        ).then(function (data) {
            var schema = data && data.addressSchema ? data.addressSchema : null;
            var levels = schema && schema.levels ? schema.levels : [];

            if (!schema || !schema.profile_code || !levels.length) {
                return null;
            }

            var cityLevels = levels
                .filter(function (level) {
                    return level.entity_type === 'city';
                })
                .map(function (level) {
                    return {
                        label: level.label,
                        placeholder: level.placeholder || '',
                        required: !!level.required
                    };
                });

            return cityLevels.length ? {
                profileCode: schema.profile_code,
                levels: cityLevels
            } : null;
        });

        return schemaMemo[key];
    }

    /**
     * Options of one cascade level.
     *
     * @param {String} profileCode
     * @param {Object} selector exactly one of {regionId} | {parentCityId}
     * @returns {Promise<Array>} {city_id, default_name, name, depth}[]
     */
    function loadLevelOptions(profileCode, selector) {
        var input = selector.regionId != null
            ? 'region_id:' + parseInt(selector.regionId, 10)
            : 'parent_city_id:' + parseInt(selector.parentCityId, 10);

        return fetchGraphQL(
            'query{addressLocations(input:{' + input +
            ',profile_code:' + JSON.stringify(String(profileCode)) +
            '}){city_id default_name name depth}}'
        ).then(function (data) {
            return data && data.addressLocations ? data.addressLocations : [];
        });
    }

    /**
     * Attach a schema-driven cascade to a surface.
     *
     * @param {Object} options
     * @param {jQuery} options.level0Select the surface's existing (level-0) select
     * @param {jQuery} options.injectAfter deeper level selects are inserted after this element
     * @param {Function} options.getCountryId () => country code string
     * @param {Function} options.getRegionId () => region id string
     * @param {Function} options.getSavedName () => persisted leaf name (native store)
     * @param {Function} options.onLeaf (leafName: String) => void — deepest selection changed
     * @param {Function} options.onSchemaResolved (hasSchema: Boolean) => void
     * @param {Function} [options.locked] () => Boolean — render selects disabled (same-as-billing)
     * @param {String} [options.selectClass] classes for injected (deeper) selects
     * @param {String} [options.fallbackPlaceholder] caption when the schema has none
     * @returns {Object} controller {refresh, clear, destroy}
     */
    function createCascade(options) {
        var state = {
            profileCode: null,
            levels: [],
            selects: [],      // [0] = provided level-0 select
            nodesByName: [],  // per level: {default_name: city_id}
            tokens: [],       // per-level in-flight request tokens
            cycle: 0,         // invalidates everything issued before the current refresh()
            hasSchema: null,
            destroyed: false
        };

        function locked() {
            return options.locked ? !!options.locked() : false;
        }

        function placeholderFor(idx) {
            var level = state.levels[idx];

            return (level && level.placeholder) || options.fallbackPlaceholder || '';
        }

        function ensureSelect(idx) {
            if (state.selects[idx]) {
                return state.selects[idx];
            }

            var $select = $('<select/>', {
                'class': (options.selectClass || 'admin__control-select') +
                    ' secomm-city-level-' + (idx + 1),
                'data-secomm-city-level': String(idx + 1),
                autocomplete: 'off'
            });

            (idx === 0 ? options.injectAfter : state.selects[idx - 1]).after($select);
            $select.on('change.secommCityCascade', function () {
                onLevelChange(idx);
            });
            state.selects[idx] = $select;
            state.nodesByName[idx] = {};
            state.tokens[idx] = 0;

            return $select;
        }

        function resetLevel(idx) {
            var $select = state.selects[idx];

            if (!$select) {
                return;
            }

            state.nodesByName[idx] = {};
            $select.empty().val('').prop('disabled', true).hide();
        }

        function fillOptions(idx, nodes, selectedName) {
            var $select = state.selects[idx];

            state.nodesByName[idx] = {};
            $select.empty();
            $select.append($('<option/>', {
                value: '',
                text: placeholderFor(idx)
            }));

            nodes.forEach(function (node) {
                var name = node.default_name || '';

                if (!name) {
                    return;
                }
                state.nodesByName[idx][name] = node.city_id;
                $select.append($('<option/>', {
                    value: name,
                    text: node.name || name
                }));
            });

            if (selectedName && state.nodesByName[idx][selectedName]) {
                $select.val(selectedName);
            }

            $select.prop('disabled', locked() || !nodes.length).show();
        }

        /**
         * Name of the deepest selection that actually carries a value.
         *
         * @returns {String}
         */
        function currentLeaf() {
            var leaf = '';

            for (var idx = 0; idx < state.selects.length; idx++) {
                var $select = state.selects[idx];

                if (!$select || !$select.is(':visible')) {
                    break;
                }

                var value = $select.val();

                if (!value) {
                    break;
                }
                leaf = value;
            }

            return leaf;
        }

        /**
         * Load the children of the selected node into level `idx` (stop-at-leaf: an empty
         * result hides this level again and the parent selection stays the leaf).
         */
        function loadChildren(idx, parentCityId, cycle) {
            var token = ++state.tokens[idx];

            state.selects[idx].prop('disabled', true);

            loadLevelOptions(state.profileCode, {
                parentCityId: parentCityId
            }).then(function (nodes) {
                if (state.destroyed || cycle !== state.cycle || token !== state.tokens[idx]) {
                    return;
                }

                if (!nodes.length) {
                    resetLevel(idx);
                    options.onLeaf(currentLeaf());

                    return;
                }

                fillOptions(idx, nodes, '');
                options.onLeaf(currentLeaf());
            });
        }

        function onLevelChange(idx) {
            var cycle = state.cycle;
            var value = state.selects[idx].val() || '';

            for (var deeper = idx + 1; deeper < state.selects.length; deeper++) {
                resetLevel(deeper);
            }
            options.onLeaf(currentLeaf());

            if (!value || idx + 1 >= state.levels.length) {
                return;
            }

            var parentId = state.nodesByName[idx][value];

            if (parentId) {
                loadChildren(idx + 1, parentId, cycle);
            }
        }

        function refreshByRegion(savedName) {
            var cycle = state.cycle;
            var regionId = options.getRegionId() || '';

            for (var idx = 1; idx < state.selects.length; idx++) {
                resetLevel(idx);
            }

            // Level 0 always renders a disabled placeholder select while no region is
            // picked ("select a region first"), so the surface's cascade container never
            // collapses to an empty box.
            ensureSelect(0).empty().append($('<option/>', {
                value: '',
                text: placeholderFor(0)
            })).val('').prop('disabled', true).show();

            if (!regionId || !state.profileCode) {
                return;
            }

            loadLevelOptions(state.profileCode, {
                regionId: regionId
            }).then(function (nodes) {
                if (state.destroyed || cycle !== state.cycle) {
                    return;
                }

                fillOptions(0, nodes, savedName);

                // Prefill walked level 0 (saved leaf names live there for depth-1
                // profiles); load the next level's options when a node was matched so a
                // deeper profile only needs the below-level re-selection. Sync the leaf
                // write-through only on a real match — surfaces keep their own rules for
                // stale stored names (free-text fallback vs clear-on-change).
                var matchedId = savedName ? state.nodesByName[0][savedName] : null;

                if (matchedId) {
                    options.onLeaf(currentLeaf());
                    if (state.levels.length > 1) {
                        loadChildren(1, matchedId, cycle);
                    }
                }
            });
        }

        function ensureSkeleton() {
            // Level 0 is the surface's select; guarantee deeper level selects exist so
            // resetLevel/fillOptions can address every schema level.
            for (var idx = 1; idx < state.levels.length; idx++) {
                ensureSelect(idx);
            }
            for (var extra = state.levels.length; extra < state.selects.length; extra++) {
                resetLevel(extra);
            }
        }

        function refresh() {
            if (state.destroyed) {
                return;
            }

            state.cycle++;
            var cycle = state.cycle;
            var country = options.getCountryId() || '';

            loadCityLevels(country).then(function (schema) {
                if (state.destroyed || cycle !== state.cycle) {
                    return;
                }

                var hasSchema = !!schema;

                if (hasSchema !== state.hasSchema) {
                    state.hasSchema = hasSchema;
                    options.onSchemaResolved(hasSchema);
                }

                state.profileCode = schema ? schema.profileCode : null;
                state.levels = schema ? schema.levels : [];
                ensureSkeleton();
                refreshByRegion(options.getSavedName() || '');
            });
        }

        function clear() {
            state.cycle++;

            for (var idx = 0; idx < state.selects.length; idx++) {
                resetLevel(idx);
            }
            options.onLeaf('');
        }

        function destroy() {
            state.destroyed = true;
            state.cycle++;

            for (var idx = 1; idx < state.selects.length; idx++) {
                if (state.selects[idx]) {
                    state.selects[idx].off('.secommCityCascade').remove();
                    state.selects[idx] = null;
                }
            }

            if (state.selects[0]) {
                state.selects[0].off('.secommCityCascade');
            }
        }

        state.selects[0] = options.level0Select;
        state.nodesByName[0] = {};
        state.tokens[0] = 0;
        options.level0Select.on('change.secommCityCascade', function () {
            onLevelChange(0);
        });

        return {
            refresh: refresh,
            clear: clear,
            destroy: destroy
        };
    }

    return {
        createCascade: createCascade,
        loadCityLevels: loadCityLevels,
        loadLevelOptions: loadLevelOptions
    };
});
