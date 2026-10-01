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
    'mage/translate',
    'mage/template',
    'mpRMA/request'
], function ($, $t, mageTemplate, mpRMARequest) {
    'use strict';

    $.widget('mageplaza.rmaTemplate', mpRMARequest, {
        options: {
            deleteTemplateUrl: {},
            insertTemplateUrl: {}
        },

        /**
         * @inheritDoc
         */
        _create: function () {
            this._bind();
        },

        /**
         * Bind handler to elements
         * @protected
         */
        _bind: function () {
            this._on(this.element, {
                'click button[data-role=template-add-row]': function (event) {
                    this._addRow(event);
                },

                'click i[data-role=template-delete-row]': function (event) {
                    this._deleteRow(event);
                },

                'click button[data-role=template-save]': function (event) {
                    this._saveTemplate(event);
                },

                'click i[data-role=template-delete]': function (event) {
                    this._deleteTemplate(event);
                },

                'click button[data-role=template-insert]': function (event) {
                    this._insertTemplate(event);
                },

                'click div[data-role=template-expand]': function (event) {
                    this._expandTemplate(event);
                },

                'click button[data-role=template-index]': function (event) {
                    var editUrl = $(event.target).attr('data-edit-url');

                    if ($(event.target).parents('button[data-role=template-index]').length > 0) {
                        editUrl = $(event.target).parents('button[data-role=template-index]').attr('data-edit-url');
                    }
                    if (typeof editUrl !== "undefined") {
                        this._initTemplateContent(editUrl);
                    } else {
                        this._initTemplateContent(this.options.templateIndexUrl);
                    }

                },

                'click a[data-role=template-edit]': function (event) {
                    this._initTemplateEditPage(event);
                }
            });
        },

        /**
         * Add store view template content row
         */
        _addRow: function (event) {
            var d       = new Date(),
                tBody   = $(event.target).parents('#mp-rma-edit-page').find('#template_base_fieldset'),
                rowHtml = mageTemplate(
                    '#mp_rma_content_row_template',
                    {
                        data: {
                            id: 'S_' + d.getTime() + '_' + d.getMilliseconds()
                        }
                    }
                );

            tBody.append(rowHtml);
        },

        /**
         * Delete store view template content row
         */
        _deleteRow: function (event) {
            $(event.target).parents('div.field-content_template.mp-template-field').remove();
        },

        /**
         * Save RMA reply template
         */
        _saveTemplate: function () {
            var el                  = this,
                templateForm        = $('#mp-rma-edit-page form#mp-template-edit-form'),
                templateStoreInputs = $('#mp-rma-edit-page #template_base_fieldset .template_select_store'),
                valueArray          = [],
                isDuplicateValue    = false;

            templateStoreInputs.each(function () {
                var value = $(this).val();

                $(this).parent().find('span.mage-error').remove();
                $(this).removeClass('duplicate');
                if (valueArray.indexOf(value) === -1) {
                    valueArray.push(value);
                } else {
                    $(this).addClass('duplicate');
                    $(this).after('<span class="mage-error">' + $t('Duplicate store view') + '</span>');
                    isDuplicateValue = true;
                }
            });

            if (templateForm.valid() && !isDuplicateValue) {
                el.options.templateLoader.show();
                $.ajax({
                    type: "POST",
                    url: templateForm.attr('action'),
                    data: templateForm.serialize(),
                    success: function (response) {
                        if (response.ajaxRedirect) {
                            window.location.href = response.ajaxRedirect;
                        }
                        if (response.status) {
                            el._initTemplateContent(el.options.templateIndexUrl);
                        }
                        el.options.templateMessage.html(response.message);
                    },
                    complete: function () {
                        el.options.templateLoader.hide();
                    }
                });
            }
        },

        /**
         * Delete RMA reply template
         */
        _deleteTemplate: function (event) {
            var el         = this,
                templateId = $(event.target).attr('data-template-id');

            el.options.templateLoader.show();
            $.ajax({
                type: "POST",
                url: el.options.deleteTemplateUrl,
                data: {template_id: templateId},
                success: function (response) {
                    if (response.ajaxRedirect) {
                        window.location.href = response.ajaxRedirect;
                    }
                    if (response.status) {
                        el._initTemplateContent(el.options.templateIndexUrl);
                    }
                    el.options.templateMessage.html(response.message);
                },
                complete: function () {
                    el.options.templateLoader.hide();
                }
            });
        },

        /**
         * Insert RMA reply template
         */
        _insertTemplate: function (event) {
            var el         = this,
                templateId = $(event.target).attr('data-template-id'),
                replyBox   = $('#request_conversation_fieldset #request_reply');

            el.options.templateLoader.show();
            if ($(event.target).parents('button[data-role=template-insert]').length > 0) {
                templateId = $(event.target).parents('button[data-role=template-insert]').attr('data-template-id');
            }
            $.ajax({
                type: "POST",
                url: el.options.insertTemplateUrl,
                data: {template_id: templateId, store_id: el.options.templateStoreID},
                success: function (response) {
                    if (response.ajaxRedirect) {
                        window.location.href = response.ajaxRedirect;
                    }
                    if (response.status) {
                        el.options.templateCtn.trigger('closeModal');
                        replyBox.val(response.reply_content);
                    }
                    el.options.replyMessage.html(response.message);
                },
                complete: function () {
                    el.options.templateLoader.hide();
                }
            });
        },

        /**
         * Expand template content box
         */
        _expandTemplate: function (event) {
            if ($(event.target).parents('.template-buttons').length === 0) {
                var item = $(event.target).parents('div.mp-template-item');

                item.find('.mp-template-content').slideToggle('fast');
            }
        },

        /**
         * Open template edit page
         */
        _initTemplateEditPage: function (event) {
            event.preventDefault();
            var editUrl = $(event.target).attr('href');

            this._initTemplateContent(editUrl);
        }
    });

    return $.mageplaza.rmaTemplate;
});