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
 * @package     Mageplaza_Lookbook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

define([
    'Magento_Ui/js/grid/columns/thumbnail',
    'jquery',
    'mage/template',
    'text!Mageplaza_Lookbook/template/grid/cells/preview.html',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function (Thumbnail, $, mageTemplate, previewTemplate) {
    'use strict';

    return Thumbnail.extend({
        defaults: {
            bodyTmpl: 'Mageplaza_Lookbook/grid/cells/html',
            fieldClass: {
                'data-grid-thumbnail-cell': true
            }
        },

        /**
         * Get content data per row
         * @param {Object} row
         * @returns {String}
         */
        getContent: function (row) {
            return row[this.index];
        },

        /**
         * Check banner type per row
         *
         * @param {Object} row
         * @returns {boolean}
         */
        getType: function (row) {
            return row[this.index + '_type'] === '1';
        },

        /**
         * Build preview.
         *
         * @param {Object} row
         */
        preview: function (row) {
            var modalHtml    = mageTemplate(
                previewTemplate,
                {
                    src: this.getSrc(row),
                    alt: this.getAlt(row),
                    link: this.getLink(row),
                    linkText: $.mage.__('Go to Details Page')
                }
                ),
                previewPopup = $('<div></div>').html(modalHtml);

            previewPopup.modal({
                innerScroll: true,
                modalClass: '_image-box',
                buttons: []
            }).trigger('openModal');
        }
    });
});
