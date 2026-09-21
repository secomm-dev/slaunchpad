/**
 * Launchpad_MageplazaTableRate — TASK-JZXM66
 *
 * Cascading City/Area select for the Mageplaza TableRate rate form (Launchpad preference
 * subclass CityForm). The `city_code` select starts server-rendered with the wildcard entry
 * (plus the stored code on edit); this component fills it from the
 * launchpad_mptablerate/city/options feed whenever the form's `region` select changes.
 *
 * The form HTML is injected into the admin modal via jQuery `.html()` (Mageplaza
 * rate/buttons.js), so inline scripts are evaluated per modal render. Scoping is marker-based
 * (`div.launchpad-city-selector` inside the same fieldset) because several rate modals can
 * coexist in the DOM with duplicate element ids — `[name="…"]` is resolved per fieldset,
 * never globally. Each marker initializes exactly once per page load; reopening a cached
 * modal does not rebind.
 *
 * Persistence contract: the select value is the raw `city_code`; an empty value is the
 * wildcard. A stored code that no longer resolves inside the region list is NEVER silently
 * wildcarded — it is appended as a raw option so a save either keeps it (owner fixes the
 * region) or the admin explicitly re-selects/clears it.
 */
define([
    'jquery'
], function ($) {
    'use strict';

    var WILDCARD_OPTION = {
        code: '',
        label: 'All / *'
    };

    /**
     * Rebuild the City/Area select from an options payload.
     *
     * @param {jQuery} $city
     * @param {Array} options [{code, label}, …]
     * @param {Boolean} preserveCurrent keep the current selection when it survives the rebuild
     * @param {Boolean} allowForeign keep a selection NOT offered by the payload as a raw
     *        option (initial edit-modal load only — a stored code is never silently
     *        wildcarded; on a user region change a foreign code falls back to the wildcard)
     */
    function applyOptions($city, options, preserveCurrent, allowForeign) {
        var current = preserveCurrent ? String($city.val() || '') : '';
        var found = false;

        $city.empty();

        $.each(options || [], function (index, option) {
            var code = String(option && option.code ? option.code : '');
            var label = String(option && option.label ? option.label : code);

            $city.append($('<option>', {
                value: code,
                text: code === '' && label === '' ? WILDCARD_OPTION.label : label
            }));

            if (code !== '' && code === current) {
                found = true;
            }
        });

        if (current !== '' && !found && allowForeign) {
            // Stored code not offered by the current region list — keep it visible/selectable.
            $city.append($('<option>', {
                value: current,
                text: current + ' (other region)'
            }));
            found = true;
        }

        $city.val(found ? current : WILDCARD_OPTION.code);
    }

    function loadOptions($region, $city, optionsUrl, preserveCurrent, allowForeign) {
        var region = String($region.val() || '');

        if (region === '' || region === '*') {
            applyOptions($city, [WILDCARD_OPTION], preserveCurrent, allowForeign);

            return;
        }

        $.getJSON(optionsUrl, {
            region: region
        }).done(function (response) {
            applyOptions($city, (response && response.options) || response, preserveCurrent, allowForeign);
        }).fail(function () {
            // Feed unavailable — keep whatever is rendered server-side; save-time validation
            // still rejects unknown/inconsistent codes.
        });
    }

    return function () {
        $('.launchpad-city-selector').each(function () {
            var marker = $(this);
            var scope;
            var $region;
            var $city;
            var optionsUrl;

            if (marker.data('lpCitySelectorInitialized')) {
                return;
            }

            scope = marker.closest('.fieldset');
            $region = scope.find('[name="region"]');
            $city = scope.find('[name="city_code"]');
            optionsUrl = marker.attr('data-options-url') || '';

            if (!$region.length || !$city.length || optionsUrl === '') {
                return;
            }

            marker.data('lpCitySelectorInitialized', true);

            $region.on('change', function () {
                // Region changed by the user: reload and reset — keep the current code only
                // if it still belongs to the new region's list.
                loadOptions($region, $city, optionsUrl, true, false);
            });

            // Initial load: keep the stored code even when the region list does not contain it.
            loadOptions($region, $city, optionsUrl, true, true);
        });
    };
});
