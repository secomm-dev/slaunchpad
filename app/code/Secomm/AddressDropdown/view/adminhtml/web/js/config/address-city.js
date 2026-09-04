/**
 * SL-013 / FEAT-007 (DEC-019/025): generic, country-agnostic dependent dropdown driver for
 * the admin config `city` field (Store Information + Shipping Origin).
 *
 * Generic mechanism (Secomm_AddressDropdown). TASK-9EX975 Slice A: the level count,
 * labels and placeholders come from the country's resolved Address Profile
 * (`addressSchema`), the options per level from `addressLocations` — through the shared
 * `schema-cascade` factory (same queries and stop-at-leaf semantics as the storefront
 * renderer). VN-specific behaviour/label/validate is owned by Secomm_VietNamAddress; this
 * file holds NO country logic (data-driven: unmapped countries fall back to the native
 * `city` input).
 *
 * Known behavioural note vs the single-level driver: a stale stored city name that no
 * longer matches any option keeps posting (the native input retains it) but the select
 * mode stays on — the admin simply re-picks. The old driver switched back to the input in
 * that case; the value contract is unchanged.
 *
 * Wired through data-mage-init on the renderer wrapper (.secomm-config-address-city).
 *
 * CONFIG-SPECIFIC: the config form's own region updater
 * (module-config/.../system/config/js.phtml) REPLACES the region_id DOM node via
 * `parentNode.innerHTML = select.outerHTML` when a country is chosen. A cached jQuery
 * reference therefore goes stale (detached) the moment a province list is rendered. To stay
 * correct we (1) re-query country/region_id FRESH on every read, and (2) bind change handlers
 * via event DELEGATION on the form so they survive the node replacement.
 */
define([
    'jquery',
    'Secomm_AddressDropdown/js/form/schema-cascade'
], function ($, schemaCascade) {
    'use strict';

    function escapeSelectorId(id) {
        return id ? '#' + id.replace(/([!"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1') : '';
    }

    return function (config, element) {
        var $root = $(element);

        if ($root.data('secommConfigAddressCityInitialized')) {
            return;
        }
        $root.data('secommConfigAddressCityInitialized', true);

        // The config form ancestor. country/region_id live elsewhere in this form (NOT inside
        // our renderer wrapper), and region_id may be replaced under us — always re-query.
        var $form = $root.closest('form');
        if (!$form.length) {
            $form = $(document); // defensive fallback so the cascade still finds sibling fields
        }

        // The config form renders ALL sections in ONE form, so several address groups (e.g.
        // store_information + origin) coexist. Suffix selectors alone would match the wrong
        // group's fields. Derive this group's country/region ids from the city field's own id
        // (same `{group}_` prefix) so each renderer instance binds only to its own siblings.
        var baseId = (config.cityInputId || '').replace(/_city$/, '');

        function findSiblingBySuffix(suffix) {
            if (baseId) {
                var $exact = $form.find(escapeSelectorId(baseId + suffix)).first();
                if ($exact.length) {
                    return $exact;
                }
            }
            // Fallback: suffix search scoped to the closest section fieldset.
            return $root.closest('fieldset').find('[id$="' + suffix + '"]').first();
        }

        var countrySelector = baseId ? escapeSelectorId(baseId + '_country_id') : '[id$="_country_id"]';
        var regionSelector = baseId ? escapeSelectorId(baseId + '_region_id') : '[id$="_region_id"]';

        // These are OUR elements inside the renderer wrapper; they are never replaced, so a
        // cached reference is safe. Deeper cascade levels are injected inside the same
        // select span, so they inherit its visibility.
        var $cityInput = $root.find(escapeSelectorId(config.cityInputId));
        var $citySelect = $root.find(escapeSelectorId(config.citySelectId));
        var $cityInputWrap = $cityInput.closest('.secomm-config-city-input');
        var $citySelectWrap = $citySelect.closest('.secomm-config-city-select');

        var currentCity = config.currentCity || $cityInput.val() || '';

        // Re-query helpers: the config region updater replaces the region_id node, so a cached
        // reference goes stale. Always read the current node from the form (scoped to our group).
        function getCountryValue() {
            var $c = findSiblingBySuffix('_country_id');
            return $c.length ? ($c.val() || '') : '';
        }

        function getRegionIdValue() {
            var $r = findSiblingBySuffix('_region_id');
            if ($r.length && $r.val()) {
                return $r.val();
            }
            return '';
        }

        function setCityMode(useSelect) {
            $citySelectWrap.toggle(useSelect);
            $cityInputWrap.toggle(!useSelect);
        }

        var cascade = schemaCascade.createCascade({
            level0Select: $citySelect,
            injectAfter: $citySelect,
            getCountryId: getCountryValue,
            getRegionId: getRegionIdValue,
            getSavedName: function () {
                return currentCity;
            },
            onLeaf: function (leafName) {
                $cityInput.val(leafName);
                currentCity = leafName;
            },
            onSchemaResolved: setCityMode,
            fallbackPlaceholder: $.mage.__('Please select a city')
        });

        function clearCitySelection() {
            $cityInput.val('');
            currentCity = '';
            cascade.clear();
        }

        // Event DELEGATION on the form (scoped to this group's ids): the config region updater
        // replaces the region_id node, so a direct bind on it would be lost. Delegated handlers
        // survive the replacement.
        $form.on('change', countrySelector, function (event) {
            if (!event.originalEvent) {
                return; // programmatic - watcher reloads
            }
            // User changed country: previous province is invalid. Reset the (current)
            // region field and clear city; the watcher reloads once the region updater runs.
            var $r = findSiblingBySuffix('_region_id');
            if ($r.length) {
                $r.val('');
            }
            clearCitySelection();
        });

        $form.on('change', regionSelector, function (event) {
            if (!event.originalEvent) {
                return; // programmatic (region updater) - watcher reloads
            }
            // User picked a province: the old city is invalid for it.
            clearCitySelection();
            cascade.refresh();
        });

        // Hydration watcher. The config region updater fetches provinces asynchronously and
        // rebuilds the region_id node (no jQuery change event). Re-query (country, region_id)
        // fresh each tick so the city list follows the real, current province. Bounded; a user
        // country/region change above clears the selection, a programmatic change preserves it.
        var watchKey = function () {
            return (getCountryValue() || '') + '|' + (getRegionIdValue() || '');
        };
        var lastKey = watchKey();
        var watchTicks = 0;
        var watchTimer = setInterval(function () {
            watchTicks += 1;
            var key = watchKey();

            if (key !== lastKey) {
                lastKey = key;
                cascade.refresh();
            }

            if (watchTicks >= 1200) { // ~5min at 250ms - covers editing a config form
                clearInterval(watchTimer);
            }
        }, 250);

        cascade.refresh();
    };
});
