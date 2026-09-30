define([
    'jquery',
    'underscore'
], function ($, _) {
    'use strict';

    return function (config) {
        // First fetch the events
        $.ajax({
            url: config.url,
            type: 'POST',
            dataType: 'json',
            data: {
                tiktok_events: config.eventTypes,
                product: config.product,
                form_key: window.FORM_KEY
            },
            success: function (response) {
                if (response.content && response.content.length) {
                    initPixel(response.content);
                }
                // Add to cart trigger remains the same
                $(document).on('ajax:addToCart', function (event, data) {
                    const response = data.response;
                    if (response && response.tiktok_events && response.tiktok_events.length) {
                        response.tiktok_events.forEach(function (eventData) {
                            if (typeof ttq !== 'undefined') {
                                ttq.track(eventData.data.event, _.extend({event_id: eventData.data.event_id}, eventData.data.properties));
                            }
                        });
                    }
                });
            },
            error: function (xhr, status, error) {
                console.error('Error fetching TikTok events:', error);
            }
        });


    };

    function initPixel(config) {
        var generalSettings = _.first(config), sourceId = '';
        if (!generalSettings) {
            return;
        }
        sourceId = generalSettings.event_source_id;
        if (!sourceId) {
            return;
        }
        !function (w, d, t) {
            w.TiktokAnalyticsObject = t;
            var ttq = w[t] = w[t] || [];
            ttq.methods = ["page", "track", "identify", "instances", "debug", "on", "off", "once", "ready", "alias", "group", "enableCookie", "disableCookie"], ttq.setAndDefer = function (t, e) {
                t[e] = function () {
                    t.push([e].concat(Array.prototype.slice.call(arguments, 0)))
                }
            };
            for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i]);
            ttq.instance = function (t) {
                for (var e = ttq._i[t] || [], n = 0; n < ttq.methods.length; n++) ttq.setAndDefer(e, ttq.methods[n]);
                return e
            }, ttq.load = function (e, n) {
                var i = "https://analytics.tiktok.com/i18n/pixel/events.js";
                ttq._i = ttq._i || {}, ttq._i[e] = [], ttq._i[e]._u = i, ttq._t = ttq._t || {}, ttq._t[e] = +new Date, ttq._o = ttq._o || {}, ttq._o[e] = n || {}, ttq._partner = ttq._partner || "Magento";
                var o = document.createElement("script");
                o.type = "text/javascript", o.async = !0, o.src = i + "?sdkid=" + e + "&lib=" + t;
                var a = document.getElementsByTagName("script")[0];
                a.parentNode.insertBefore(o, a)
            };
            ttq.load(generalSettings.event_source_id);
        }(window, document, 'ttq');
        if (generalSettings.data && generalSettings.data.user) {
            ttq.instance(generalSettings.event_source_id).identify(generalSettings.data.user);
        }
        trackEvents(config)
    }

    function trackEvents(events) {
        if (!_.isArray(events) || !window.ttq) {
            return;
        }

        _.each(events, function (event) {
            var properties = (event.data.event !== 'Pageview')
                ? _.extend(
                    {event_id: event.data.event_id},
                    event.data.properties
                ) : {event_id: event.data.event_id};

            ttq.instance(event.event_source_id)
                .track(
                    event.data.event,
                    properties
                );
        });
    }
});
