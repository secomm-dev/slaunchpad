define([
    'jquery',
    'ko',
    'uiComponent',
    'mage/cookies'
], function ($, ko, Component) {
    'use strict';

    return Component.extend({
        defaults: {
            isToolBarAllowed: ko.observable(false),
            sections: ko.observable([])
        },

        initialize: function (config) {
            this._super();

            var self = this;
            this.sections = ko.observableArray(config.sections);

            $.ajax({
                url: config.url,
                type: 'GET',
                success: function (response) {
                    self.isToolBarAllowed(response.isToolBarAllowed);

                    if (response.isToolBarAllowed) {
                        self.configureToolbar();
                    }
                }
            });
        },

        configureToolbar: function () {
            var $toolbar = $('.mst-seo-toolbar__toolbar');
            var cookieName = "mst-seo-toolbar";

            $toolbar.removeClass('_disabled');

            if ($.mage.cookies.get(cookieName) === 'off') {
                $toolbar.removeClass('_hidden').addClass('_hidden');
            }    else {
                $toolbar.removeClass('_hidden');
            }

            $('[data-role=close]', $toolbar).on('click', function (e) {
                e.preventDefault();
                $toolbar.removeClass('_hidden').addClass('_hidden');
                $.mage.cookies.set(cookieName, 'off', {expires: new Date(Date.now() + (24 * 60 * 60)), path: '/'});
            });

            $('[data-role=header]', $toolbar).on('click', function (e) {
                if (!$(e.target).hasClass('icon-close')) {
                    e.preventDefault();
                    $toolbar.removeClass('_hidden');
                    $.mage.cookies.set(cookieName, 'on', {expires: new Date(Date.now() + (24 * 60 * 60)), path: '/'});
                }
            });
        }
    });
});
