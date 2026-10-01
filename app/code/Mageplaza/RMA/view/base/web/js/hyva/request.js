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

function initRequest() {
    return {
        options: {
            templateCtn: document.querySelector('#mp-rma-template-container'),
            templateContent: document.querySelector('#mp-rma-template-container .page-columns'),
            templateMessage: document.querySelector('#mp-rma-template-container .mp-rma-message.template-message'),
            templateLoader: document.querySelector('#mp-rma-template-container .mp-rma-image-loader'),
            replyMessage: document.querySelector('#request_conversation_fieldset .mp-rma-message.reply-message')
        },
        init: function () {
            const storeInput = document.querySelector('.field-store_view input[name="request[store_id]"]');
            this.options.templateStoreID = storeInput ? storeInput.val() : null;

            document.addEventListener('click', function(event) {
                if (event.target && event.target.matches('div[data-role="inspect-file"]')) {
                    this._inspectFile(event);
                }
            }.bind(this));
        },

        /**
         * Init RMA reply template popup content
         */
        _initTemplateContent: function(url) {
            var el = this;

            el.options.templateContent.innerHTML = '';
            el.options.templateMessage.innerHTML = '';
            el.options.templateLoader.style.display = 'block';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'store_id=' + el.options.templateStoreID
            })
                .then(response => response.json())
                .then(response => {
                    if (response.ajaxRedirect) {
                        window.location.href = response.ajaxRedirect;
                    }
                    if (response.status) {
                        el.options.templateContent.innerHTML = response.template_html;
                        var event = new Event('contentUpdated');
                        el.options.templateContent.dispatchEvent(event);
                    }
                })
                .catch(error => console.error('Error:', error))
                .finally(() => {
                    el.options.templateLoader.style.display = 'none';
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
            var fileContainer = event.target;

            if (fileContainer.parentNode && fileContainer.parentNode.classList.contains('file-inspect')) {
                fileContainer = fileContainer.parentNode;
            }

            window.location.href = this.options.downloadFileUrl + '?file_info=' + fileContainer.getAttribute('data-file-info');
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
            i = Math.floor(Math.log(bytes) / Math.log(1024));

            return (Math.round(bytes / Math.pow(1024, i), 2)) + ' ' + sizes[i];
        }
    }
}
