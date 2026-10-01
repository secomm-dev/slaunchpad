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
    'underscore',
    'jquery',
    'mage/translate',
    'mpRMA/request',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/modal'
], function (_, $, $t, mpRMARequest, alert) {
    'use strict';

    $.widget('mageplaza.rmaReply', mpRMARequest, {
        options: {
            uploadUrl: {},
            loadReplyUrl: {},
            saveReplyUrl: {},
            fileNumber: 0,
            fileContainer: $('form#edit_form .mp-files'),
            conversationCtn: $('#request_conversation_fieldset #mp-request-conversation-container'),
            conversationLoader: $('#request_conversation_fieldset #mp-request-conversation-container .mp-rma-image-loader')
        },

        /**
         * @inheritDoc
         */
        _create: function () {
            this._loadReply(0);
            this._bind();
        },

        /**
         * Bind handler to elements
         * @protected
         */
        _bind: function () {
            this._on(this.element, {
                'change input[data-role=upload-file]': function (event) {
                    this._uploadFile(event);
                },

                'click i[data-role=delete-button]': function (event) {
                    this._remove(event);
                },

                'click button[data-role=submit-reply]': function (event) {
                    this._submitReply(event);
                },

                'click div[data-role=tmp-inspect-file]': function (event) {
                    this._inspectFile(event);
                },

                'click button[data-role=template-popup]': function () {
                    this._initTemplatePopup();
                }
            });
        },

        /**
         * Upload reply attachment files
         */
        _uploadFile: function (event) {
            var el       = this,
                formData = new FormData(),
                files    = event.target.files;

            el.options.fileContainer.slideDown('fast');
            _.each(files, function (file) {
                var d      = new Date(),
                    fileId = d.getTime() + '_' + d.getMilliseconds();

                el.options.fileContainer.find('.mp-file-box-container').append('<div id="mp-file-box-' + fileId + '" class="mp-file-box">' +
                    '<i class="fa fa-times" data-role="delete-button" aria-hidden="true"></i>' +
                    '<span class="file-info uploading">' + file.name +
                    '<span class="file-size"> (' + el.bytesToSize(file.size) + ')</span></span>' +
                    '</div>');
                if (formData) {
                    formData.delete('file');
                    formData.delete('file_id');
                    formData.append('file', file);
                    formData.append('file_id', fileId);
                    $.ajax(
                        {
                            type: "POST",
                            url: el.options.uploadUrl,
                            data: formData,
                            processData: false,
                            contentType: false,
                            success:
                                function (response) {
                                    if (response.ajaxRedirect) {
                                        window.location.href = response.ajaxRedirect;
                                    }
                                    var fileBox = $('.mp-files #mp-file-box-' + d.getTime() + '_' + d.getMilliseconds() + '');

                                    if (response.status) {
                                        fileBox.append(response.file_html);
                                        fileBox.find('span.file-info').removeClass('uploading');
                                        el.options.fileNumber++;
                                        el.options.fileContainer.find('span.attachment-num').text(el.options.fileNumber);
                                    } else {
                                        fileBox.find('span.file-info').removeClass('uploading');
                                        fileBox.find('span.file-info').addClass('error');
                                        alert({
                                            content: $t(response.error)
                                        });
                                    }
                                }
                        }
                    );
                }
            });
            $(event.target).val('');
        },

        /**
         * Submit request reply
         */
        _submitReply: function (event) {
            var el          = this,
                requestForm = $(event.target).parents('form#edit_form'),
                replyLoader = $('#request_conversation_fieldset #mp-request-reply-container .mp-rma-image-loader'),
                submitBtn   = $('button#mp-replies-submit[data-role=submit-reply]');

            if (el.options.fileNumber === 0 && requestForm.find('textarea[name="reply[content]"]').val() === '') {
                el.options.replyMessage.html(this._getErrorMessage($t('Please enter the comments.')));
            } else {
                submitBtn.prop('disabled', true);
                replyLoader.show();
                $.ajax({
                    type: "POST",
                    url: el.options.saveReplyUrl,
                    data: requestForm.serialize(),
                    success: function (response) {
                        if (response.ajaxRedirect) {
                            window.location.href = response.ajaxRedirect;
                        }
                        if (response.requestRedirect) {
                            window.location.href = response.requestRedirect;
                        }
                        if (response.status) {
                            requestForm.find('textarea[name="reply[content]"]').val('');
                            requestForm
                            .find('input[name="reply[is_customer_notified]"], input[name="reply[is_visible_on_front]"]')
                            .val('').prop('checked', false);
                            el.options.fileContainer.find('.mp-file-box-container').html('');
                            el.options.fileNumber = 0;
                            el.options.fileContainer.find('span.attachment-num').text(el.options.fileNumber);
                            el.options.fileContainer.slideUp('fast');
                            el.options.conversationCtn.find('.mp-request-conversation').html(response.conversation);
                            el.options.conversationCtn.find('.mp-request-conversation').trigger('contentUpdated');
                        }
                        el.options.replyMessage.html(response.message);

                    },
                    complete: function () {
                        replyLoader.hide();
                        submitBtn.prop('disabled', false);
                    }
                });
            }
        },

        /**
         * Remove reply attachment files
         */
        _remove: function (event) {
            if ($(event.target).parent().find('.error').length === 0) {
                this.options.fileNumber--;
            }
            $(event.target).parent().remove();
            this.options.fileContainer.find('span.attachment-num').text(this.options.fileNumber);
            if (this.options.fileContainer.find('.mp-file-box').length === 0) {
                this.options.fileContainer.slideUp('fast');
            }
        },

        /**
         * Load request reply
         */
        _loadReply: function (loadAll) {
            var el        = this,
                requestId = $('form#edit_form #request_request_id').val(),
                customerEmail = $('form#edit_form #mp-reply-email').val();

            el.options.conversationLoader.show();
            $.ajax({
                type: "POST",
                url: el.options.loadReplyUrl,
                data: {
                    request_id: requestId === '' ? 0 : requestId,
                    load_all: loadAll,
                    customer_email: customerEmail
                },
                success: function (response) {
                    if (response.ajaxRedirect) {
                        window.location.href = response.ajaxRedirect;
                    }
                    if (response.requestRedirect) {
                        window.location.href = response.requestRedirect;
                    }
                    el.options.conversationCtn.find('.mp-request-conversation').html(response.conversation);
                    if (response.status) {
                        el.options.conversationCtn.find('.mp-request-conversation').trigger('contentUpdated');
                    }
                },
                complete: function () {
                    el.options.conversationLoader.hide();
                }
            });
        },

        /**
         * Init RMA reply template popup
         */
        _initTemplatePopup: function () {
            this.options.templateCtn.modal({
                type: 'slide',
                innerScroll: true,
                modalClass: 'mp-rma-template-box',
                buttons: []
            });
            this.options.templateCtn.trigger('openModal');
            this._initTemplateContent(this.options.templateIndexUrl);
        }
    });

    return $.mageplaza.rmaReply;
});