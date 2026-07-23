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
 * @package     Mageplaza_OscUltimate
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

define(['jquery', 'jquery-ui-modules/widget'], function ($) {
    'use strict';

    $.widget('mageplaza.osc_ultimate_field_position', {
        _create: function () {
            this.initGrid();
        },

        initGrid: function () {
            var selector = '#' + this.options.blockId + ' ',
                list     = $(selector + '.sortable-list');

            var options = {
                tolerance: 'pointer',
                connectWith: '.sortable-list',
                dropOnEmpty: true,
                containment: 'body',
                cancel: '.ui-state-disabled',
                placeholder: 'suggest-position',
                zIndex: 10,
                scroll: false,
                update: function(event, ui) {
                    var ulElement = $(this),
                        liCount =$(event.target).find("li").length;
                    if (liCount === 0) {
                        ulElement.addClass('_require');
                        ulElement.html('<span class="column-null">'
                            + $.mage.__('This column cannot be blank.') + '</span>');
                    }else {
                        ulElement.removeClass('_require');
                    }
                }
            };
            list.sortable(options).disableSelection();
        }
    });

    return $.mageplaza.osc_ultimate_field_position;
});
