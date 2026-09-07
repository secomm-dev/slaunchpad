/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'Magento_Customer/js/section-config',
    'domReady!'
], function($, customerData, sectionConfig) {
    'use strict';

    /**
     * Track FB pixel data
     */
    return function () {
        function pixelDataTrack(data, sectionName) {
            if (!data.mf_fb_pixel_data) return;

            for (let i = 0; i < data.mf_fb_pixel_data.length; i++) {
                let eventName = Object.keys(data.mf_fb_pixel_data[i])[0];
                let content = data.mf_fb_pixel_data[i][eventName];
                if (!window.fbq) continue;
                fbq('track', eventName, content, {'eventID': eventName + '.' + Math.floor(Math.random() * 1000000) + '.' + Date.now(), "event_source_url": window.location.href, "referrer_url": document.referrer });
            }
            delete data.mf_fb_pixel_data;
            customerData.set(sectionName, data);
        }

        let sectionNames = [];
        if (sectionConfig.getSectionNames) {
            sectionNames = sectionConfig.getSectionNames();
        }
        if (!sectionNames || !sectionNames.length) {
            sectionNames = ['customer','cart','wishlist'];
        }

        let sectionName, section;
        for (let i = 0; i < sectionNames.length; i++) {
            sectionName = sectionNames[i];
            section = customerData.get(sectionName);

            (function(section, sectionName) {
                section.subscribe(function (data) {
                    pixelDataTrack(data, sectionName);
                });
            })(section, sectionName);

            pixelDataTrack(section(), sectionName);
        }
    }
});
