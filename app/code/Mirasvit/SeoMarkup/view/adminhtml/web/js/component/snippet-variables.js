define([
    'jquery',
    'underscore',
    'ko',
    'uiComponent'
], function ($, _, ko, Component) {
    'use strict';

    return Component.extend({
        hideTimeout: null,
        $wrapper:    null,
        editor:      null,

        defaults: {
            template:     'Mirasvit_SeoMarkup/component/snippet-variables',
            wrapperClass: 'mst-seo-content__component-template-syntax',
            scopeData:    []
        },

        initialize: function () {
            this._super();

            var templatesTimer = setInterval(function () {
                if ($('.mst-seo-content__component-template-syntax-wrapper').length
                    && $('.CodeMirror').length) {
                    clearInterval(templatesTimer);
                    this.init();
                }
            }.bind(this), 250);

            return this;
        },

        init: function () {
            var html = $('.mst-seo-content__component-template-syntax-wrapper').html();

            this.$wrapper = $('<div/>')
                .addClass(this.wrapperClass)
                .html(html);

            $('body').append(this.$wrapper);

            this.editor = $('.CodeMirror')[0].CodeMirror;

            this.editor.on('focus', function () {
                clearTimeout(this.hideTimeout);
                this.$wrapper.addClass('_visible');
            }.bind(this));

            this.editor.on('blur', function () {
                this.hideTimeout = setTimeout(function () {
                    this.$wrapper.removeClass('_visible');
                }.bind(this), 500);
            }.bind(this));

            this.$wrapper.on('click', function () {
                clearTimeout(this.hideTimeout);
            }.bind(this));

            $('.close', this.$wrapper).on('click', function () {
                this.$wrapper.removeClass('_visible');
            }.bind(this));

            $('._variable', this.$wrapper).dblclick(function (e) {
                e.preventDefault();

                var text     = e.target.innerText.replace(/^\[|\]$/g, '');
                var variable = '[' + text + ']';
                var cursor   = this.editor.getCursor();

                this.editor.replaceRange(variable, cursor);
                this.editor.focus();
            }.bind(this));
        }
    });
});
