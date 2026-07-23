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

define(['jquery', 'mage/translate'], function ($) {
    'use strict';

    $.widget('mageplaza.osc_field_tabs', {
        _create: function () {
            this.initTabEvent();
            this.initSaveEvent();
        },

        getColspan: function (elem) {
            if (elem.hasClass('wide')) {
                return 12;
            } else if (elem.hasClass('medium')) {
                return 9;
            } else if (elem.hasClass('short')) {
                return 3;
            }

            return 6;
        },

        initSaveEvent: function () {
            var self = this;

            $('.mposc-save-position').on('click', function () {
                self.savePosition(self.options.url);
            });
            $('a.button.checkout-steps').on('click', function () {
                $.ajax({
                    method: 'post',
                    showLoader: true,
                    url: self.options.urlCheckoutSteps,
                    data: {
                        codeCheckoutSteps: $('.checkout-steps._active').attr('value')
                    },
                    success: function (response) {
                        $('#mposc-checkout-step').html(response.block_html);
                        $('#mposc-checkout-step .mposc-field-container').show();
                        self.initGrid()
                    }
                });
            });
        },

        initGrid: function () {
            var selector = '#mposc-checkout-step ',
                list     = $(selector + '.sortable-list'),
                field, elemWidth,
                options  = {
                    tolerance: 'pointer',
                    connectWith: '.sortable-list',
                    dropOnEmpty: true,
                    containment: 'body',
                    cancel: '.ui-state-disabled',
                    placeholder: 'suggest-position',
                    zIndex: 10,
                    scroll: false,
                    start: function (e, hash) {
                        if (hash.item.hasClass('wide')) {
                            hash.placeholder.addClass('wide');
                        }

                        if (hash.item.hasClass('medium')) {
                            hash.placeholder.addClass('medium');
                        }

                        if (hash.item.hasClass('short')) {
                            hash.placeholder.addClass('short');
                        }
                    }
                };

            list.sortable(options);

            $(selector + '.sortable-list li').disableSelection();
            $(selector + '.sortable-list li').addClass('f-left');

            $(selector + '.containment ul li .attribute-label').resizable({
                maxHeight: 40,
                minHeight: 40,
                zIndex: 10,
                cancel: '.ui-state-disabled',
                helper: 'ui-resizable-border',
                stop: function (e, ui) {
                    field     = ui.element.parent();
                    elemWidth = ui.element.width() / 2;

                    field.removeClass('wide');
                    field.removeClass('medium');
                    field.removeClass('short');

                    if (elemWidth < field.width() * 0.3) {
                        field.addClass('short');
                    } else if (elemWidth > field.width() * 0.6 && elemWidth < field.width() * 0.8) {
                        field.addClass('medium');
                    } else if (elemWidth > field.width() * 0.8) {
                        field.addClass('wide');
                    }

                    ui.element.css({width: ''});
                }
            });
        },

        initTabEvent: function () {
            var elem = $('#mposc-field-tabs .action-default'), eleBlock = $('#mposc-manage .action-default'),
                selectElem                                              = $('#layout-checkout-page');

            $("#position-save-messages").insertBefore('div#container');
            $('#' + selectElem.val()).addClass('_active');

            selectElem.on('change', function () {
                $('.page-layout').removeClass('_active');
                $('#' + this.value).addClass('_active');
            });

            elem.on('click', function () {
                var href = $('#mposc-field-tabs ._active').attr('href');
                elem.removeClass('_active');
                $(this).addClass('_active');
                $(href).hide();
                $(this.getAttribute('href')).show();

                if ($(this).parent().children('.action-default._active').index() > 0) {
                    $('#add_customer_attr').hide();
                    $('#add_order_attr').show();
                } else {
                    $('#add_customer_attr').show();
                    $('#add_order_attr').hide();
                }

                return false;
            });

            eleBlock.on('click', function () {
                eleBlock.removeClass('_active');
                $(this).addClass('_active');
                $('.mposc-field-container').hide();
                $(this.getAttribute('href')).show();

                if (this.getAttribute('href') === '#mposc-manage-fields') {
                    var href = $('#mposc-field-tabs ._active').attr('href');
                    if (href === '#mposc-checkout-step'){
                        $('#mposc-field-tabs ._active').trigger('click');
                    }
                    $(href).show();
                }

                return false;
            });

            if (window.location.hash) {
                $('[href=' + window.location.hash + ']').trigger('click');
            } else {
                $(eleBlock[0]).trigger('click');
                $(elem[0]).trigger('click');
            }
        },

        savePosition: function (url) {
            var self = this, fields = [], oaFields = [], manageBlock = [], field = {}, layout, parent = null;
            $('#position-save-messages').html('');
            if ($('.sortable-list._require').length) {
                $('#position-save-messages').html('<div class="message message-error error">' + '<span>'
                    + $.mage.__('The columns cannot be leave empty. Please try again.') + '</span>' + '</div>');
                return;
            }
            $('.sorted-wrapper .sortable-item').each(function (index, el) {
                parent = $(el).parents('.mposc-field-container');

                field = {
                    code: $(el).attr('data-code'),
                    colspan: self.getColspan($(el)),
                    required: !!$(el).find('.attribute-required input').is(':checked')
                };

                if ($(el).parents('#mposc-address-information').length) {
                    fields.push(field);
                } else if (!$(el).hasClass('ui-state-disabled')) {
                    field.bottom = parent.find('#' + $(el).attr('id')).index() > parent.find('.ui-state-disabled').index();

                    oaFields.push(field);
                }

            });
            layout            = $('#layout-checkout-page').find(":selected").val();
            var sortable_item = '#' + layout + ' .sortable-item', col1, col2, col3, data = [];
            if (layout === '3columns' || layout === '2columns') {
                data = {
                    'mp-col-1': [], 'mp-col-2': [], 'mp-col-3': []
                };
                col1 = '#' + layout + ' .mp-col-1' + ' .sortable-item';
                col2 = '#' + layout + ' .mp-col-2' + ' .sortable-item';
                $.each($(col1), function (index, item) {
                    data['mp-col-1'].push($(item).attr('data-code'))
                });
                $.each($(col2), function (index, item) {
                    data['mp-col-2'].push($(item).attr('data-code'))
                });
                if (layout === '3columns') {
                    col3 = '#' + layout + ' .mp-col-3' + ' .sortable-item';
                    $.each($(col3), function (index, item) {
                        data['mp-col-3'].push($(item).attr('data-code'))
                    })
                }
                data = Object.entries(data);
            } else {
                $.each($(sortable_item), function (index, item) {
                    if ($(item).attr('data-code')) {
                        data[index] = $(item).attr('data-code')
                    }
                })
            }

            manageBlock = {
                layout: layout,
                data: data,
                ScopeId: $('#store_switcher').val(),
                useDefault: $('.use-default').is(':checked')
            };

            $.ajax({
                method: 'post', showLoader: true, url: url, data: {
                    fields: JSON.stringify(fields),
                    oaFields: JSON.stringify(oaFields),
                    manageBlock: JSON.stringify(manageBlock)
                }, success: function (response) {
                    $('#position-save-messages').html('<div class="message message-' + response.type + ' ' + response.type + ' ">' + '<span>' + response.message + '</span>' + '</div>');
                }
            });
        }
    });

    return $.mageplaza.osc_field_tabs;
});
