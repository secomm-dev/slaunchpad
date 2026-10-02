/**
 * Secomm_AddressDropdown — TASK-Z6SK3T / DEC-TASKZ6SK3T-001.
 *
 * Shared page-session cache for the canonical address GraphQL surface:
 *  - addressSchema(country_id)   — memoized per country
 *  - addressLocations(profile)   — memoized per "<profile_code>|<region_id>",
 *                                  in-flight requests deduped (shipping + billing
 *                                  components of one page share a single POST)
 *
 * Lifetime: page session only — no sessionStorage. A scheme re-import renumbers
 * city_ids, so persistence across pages must never be assumed.
 *
 * The legacy `GetListCity` endpoint is NOT served here: storefront consumers migrate
 * to `addressLocations` (canonical, @cache(cacheable: true)).
 */
define([], function () {
    'use strict';

    var schemaMemo = {};        // "<countryId>" -> schema payload | null
    var schemaPending = {};     // "<countryId>" -> Promise
    var locationsMemo = {};     // "<profileCode>|<regionId>" -> nodes array
    var locationsPending = {};  // "<profileCode>|<regionId>" -> Promise

    function graphqlUrl() {
        return (window.BASE_URL || '/').replace(/index\.php\/?$/, '') + 'graphql';
    }

    function post(query) {
        return window.fetch(graphqlUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: query }),
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        });
    }

    /**
     * @param {String} countryId ISO code (e.g. "VN")
     * @returns {Promise<Object|null>} schema payload or null when unmapped/unavailable
     */
    function getSchema(countryId) {
        var key = String(countryId || '');

        if (!key) {
            return Promise.resolve(null);
        }
        if (Object.prototype.hasOwnProperty.call(schemaMemo, key)) {
            return Promise.resolve(schemaMemo[key]);
        }
        if (schemaPending[key]) {
            return schemaPending[key];
        }

        schemaPending[key] = post(
            'query{addressSchema(input:{country_id:"' + key + '"})' +
            '{profile_code levels{entity_type label placeholder}}}'
        ).then(function (response) {
            var schema = response && response.data && response.data.addressSchema;
            schemaMemo[key] = schema && schema.profile_code ? schema : null;
            delete schemaPending[key];
            return schemaMemo[key];
        }).catch(function (error) {
            schemaMemo[key] = null;
            delete schemaPending[key];
            console.error('address-location-cache: schema request failed:', error);
            return null;
        });

        return schemaPending[key];
    }

    /**
     * @param {String} countryId ISO code of the address being edited
     * @param {Number|String} regionId directory region id (cast to Int server-side)
     * @returns {Promise<Array>} addressLocations nodes ([] when unmapped/empty)
     */
    function getLocations(countryId, regionId) {
        var parsedRegionId = parseInt(regionId, 10);

        if (!parsedRegionId || parsedRegionId <= 0) {
            return Promise.resolve([]);
        }

        return getSchema(countryId).then(function (schema) {
            var profileCode = schema && schema.profile_code;

            if (!profileCode) {
                // Unmapped country: no canonical dataset — native city handling applies.
                return [];
            }

            var key = profileCode + '|' + parsedRegionId;

            if (locationsMemo[key]) {
                return locationsMemo[key];
            }
            if (locationsPending[key]) {
                return locationsPending[key];
            }

            locationsPending[key] = post(
                'query{addressLocations(input:{region_id:' + parsedRegionId +
                ',profile_code:"' + profileCode + '"})' +
                '{city_id default_name label}}'
            ).then(function (response) {
                var nodes = response && response.data && response.data.addressLocations;
                locationsMemo[key] = Array.isArray(nodes) ? nodes : [];
                delete locationsPending[key];
                return locationsMemo[key];
            }).catch(function (error) {
                delete locationsPending[key];
                console.error('address-location-cache: locations request failed:', error);
                return [];
            });

            return locationsPending[key];
        });
    }

    /**
     * Invalidate the locations memo (call on country change). The schema memo is
     * keyed per country and stays valid.
     */
    function clear() {
        locationsMemo = {};
        locationsPending = {};
    }

    return {
        getSchema: getSchema,
        getLocations: getLocations,
        clear: clear
    };
});
