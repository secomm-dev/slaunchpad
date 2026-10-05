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
    'jquery',
    'jquery/ui'
], function ($) {
    'use strict';

    $.widget('mageplaza.rmaForm', {
        options: {
            zipCode: '#mprma-zip', // Search by zip code.
            emailAddress: '#mprma-email', // Search by email address.
            searchType: '#quick-search-type-id' // Search element used for choosing between the two.
        },

        /** @inheritdoc */
        _create: function () {
            $(this.options.searchType).on('change', $.proxy(this._showIdentifyBlock, this)).trigger('change');
        },

        /**
         * Show either the search by zip code option or the search by email address option.
         * @private
         * @param e - Change event. Event target value is either 'zip' or 'email'.
         */
        _showIdentifyBlock: function (e) {
            var value = $(e.target).val();

            $(this.options.zipCode).toggle(value === 'zip');
            $(this.options.emailAddress).toggle(value === 'email');
        }
    });

    return $.mageplaza.rmaForm;
});
