define([
    'jquery'
], function ($) {
    'use strict';

    $.widget('mage.tiktokConnect', {
        options: {
            url: ''
        },

        /**
         * Bind handlers to events
         */
        _create: function () {
            this._on({
                'click': $.proxy(this._open, this)
            });
        },

        /**
         * Method triggers open window
         * @private
         */
        _open: function () {
            var width = 600;
            var height = 400;
            var left = (screen.width / 2) - (width / 2);
            var top = (screen.height / 2) - (height / 2);
            window.open(
                this.options.url,
                "ExternalDataHandler to TikTok",
                "toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, copyhistory=no, width="+width+", height="+height+", top="+top+", left="+left
            );
        }
    });

    return $.mage.tiktokConnect;
});
