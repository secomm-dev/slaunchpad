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

    $.widget('mageplaza.osc_ultimate_store_switcher', {
        _create: function () {
            this.initGrid();
        },
        initGrid: function () {
            var self           = this,
                storesList     = $('[data-role=stores-list]'),
                checkedDefault = $('.use-default'),
                labelDefault   = $('.label-default'),
                url            = this.options.url;

            storesList.on('click', '[data-value]', function (event) {
                $('.dropdown-menu li').removeClass('current');
                $('.dropdown-menu a').removeClass('current');
                checkedDefault.prop("checked", false);
                var val           = $(event.target).data('value'),
                    switcher      = $('[data-value=' + val + ']'),
                    useDefault    = checkedDefault.is(':checked'),
                    valueToChange = 1;
                self.reloadBlock(val, switcher, useDefault, url, valueToChange);
                if (val === 0){
                    labelDefault.text($.mage.__('Use system value'));
                }else if(val < 0){
                    labelDefault.text($.mage.__('Use Default'));
                }else{
                    labelDefault.text($.mage.__('Use Website'));
                }
                switcher.addClass('current');
                $('.store-switcher.store-view .active').removeClass('active');
            });
            checkedDefault.on('click', function () {
                var val           = $('#store_switcher').val(),
                    switcher      = $('[data-value=' + val + ']'),
                    useDefault    = checkedDefault.is(':checked'),
                    valueToChange = 0;
                self.reloadBlock(val, switcher, useDefault, url, valueToChange)
            });

        },
        reloadBlock: function (id, switcher, useDefault, url, valueToChange) {
            var self = this;
            $.ajax({
                method: 'post',
                type: 'post',
                showLoader: true,
                url: url,
                data: {
                    id: id,
                    useDefault: useDefault
                },
                success: function (response) {
                    $('#store_switcher').val(id);
                    $('#store-change-button').text(switcher.text());
                    $('#' + response.layout).html(response.block_html);
                    var selector = '#' + response.layout,
                        list     = $(selector + ' .sortable-list'),
                        checkedDefault = $('.use-default'),
                        options  = {
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
                    $('#layout-checkout-page').val(response.layout).change();
                    list.sortable(options).disableSelection();
                    if (response.useDefaultResponse && valueToChange) {
                        checkedDefault.trigger('click');
                    }
                    if (checkedDefault.is(':checked')) {
                        $('#manage-block').addClass('use-system');
                    } else {
                        $('#manage-block').removeClass('use-system');
                    }
                }
            });
        }
    });

    return $.mageplaza.osc_ultimate_store_switcher;
});
