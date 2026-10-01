/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

define([
    'jquery'
], function ($) {
    'use strict';

    $.widget('mageplaza.rmaRequest', {
        options: {
            downloadFileUrl: {},
            templateIndexUrl: {},
            templateStoreID: $('.field-store_view input[name="request[store_id]"]').val(),
            templateCtn: $('#mp-rma-template-container'),
            templateContent: $('#mp-rma-template-container .page-columns'),
            templateMessage: $('#mp-rma-template-container .mp-rma-message.template-message'),
            templateLoader: $('#mp-rma-template-container .mp-rma-image-loader'),
            replyMessage: $('#request_conversation_fieldset .mp-rma-message.reply-message')
        },

        /**
         * @inheritDoc
         */
        _create: function () {
            this._on(this.element, {
                'click div[data-role=inspect-file]': function (event) {
                    this._inspectFile(event);
                }
            });
        },

        /**
         * Init RMA reply template popup content
         */
        _initTemplateContent: function (url) {
            var el = this;

            el.options.templateContent.html('');
            el.options.templateMessage.html('');
            el.options.templateLoader.show();
            $.ajax({
                type: "POST",
                url: url,
                data: {store_id: el.options.templateStoreID},
                success: function (response) {
                    if (response.ajaxRedirect) {
                        window.location.href = response.ajaxRedirect;
                    }
                    if (response.status) {
                        el.options.templateContent.html(response.template_html);
                        el.options.templateContent.trigger('contentUpdated');
                    }
                },
                complete: function () {
                    el.options.templateLoader.hide();
                }
            });
        },

        /**
         * Get error message html
         */
        _getErrorMessage: function (text) {
            return '<div class="messages">' +
                '<div class="message message-error error">' +
                '<div data-ui-id="magento-framework-view-element-messages-0-message-error">' +
                text +
                '</div>' +
                '</div>' +
                '</div>';
        },

        /**
         * Inspect reply attachment files
         */
        _inspectFile: function (event) {
            var fileContainer = $(event.target);

            if (fileContainer.parent('div.file-inspect').length) {
                fileContainer = $(event.target).parent('div.file-inspect');
            }
            window.location.href = this.options.downloadFileUrl + '?file_info='
                + fileContainer.attr('data-file-info');
        },

        /**
         * Formats incoming bytes value to a readable format.
         *
         * @param {Number} bytes
         * @returns {String}
         */
        bytesToSize: function (bytes) {
            var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'],
                i;

            if (bytes === 0) {
                return '0 Byte';
            }
            i = window.parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));

            return Math.round(bytes / Math.pow(1024, i), 2) + ' ' + sizes[i];
        }
    });

    return $.mageplaza.rmaRequest;
});