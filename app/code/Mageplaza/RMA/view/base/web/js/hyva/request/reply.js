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

function initReply(uploadUrl, loadReplyUrl, saveReplyUrl) {
    return {
        options: {},
        init: function () {
            this.options.uploadUrl          = uploadUrl;
            this.options.loadReplyUrl       = loadReplyUrl;
            this.options.saveReplyUrl       = saveReplyUrl;
            this.options.fileNumber         = 0;
            this.options.fileContainer      = document.querySelector('form#edit_form .mp-files');
            this.options.conversationCtn    = document.querySelector('#request_conversation_fieldset #mp-request-conversation-container');
            this.options.conversationLoader = document.querySelector('#request_conversation_fieldset #mp-request-conversation-container .mp-rma-image-loader');
            this._loadReply(0);
            this._bind();
        },

        /**
         * Bind handler to elements
         * @protected
         */
        _bind: function () {
            var self = this;

            document.querySelectorAll('input[data-role=upload-file]').forEach(function (input) {
                input.addEventListener('change', function (event) {
                    self._uploadFile(event);
                });
            });

            document.querySelectorAll('i[data-role=delete-button]').forEach(function (icon) {
                icon.addEventListener('click', function (event) {
                    self._remove(event);
                });
            });

            document.querySelectorAll('button[data-role=submit-reply]').forEach(function (button) {
                button.addEventListener('click', function (event) {
                    self._submitReply(event);
                });
            });

            document.querySelectorAll('div[data-role=tmp-inspect-file]').forEach(function (div) {
                div.addEventListener('click', function (event) {
                    self._inspectFile(event);
                });
            });

            document.querySelectorAll('button[data-role=template-popup]').forEach(function (button) {
                button.addEventListener('click', function () {
                    self._initTemplatePopup();
                });
            });
        },

        /**
         * Upload reply attachment files
         */
        _uploadFile: function (event) {
            var el = this,
                formData = new FormData(),
                files = event.target.files;

            el.options.fileContainer.style.display = 'block';

            Array.from(files).forEach(function (file) {
                var d = new Date(),
                    fileId = d.getTime() + '_' + d.getMilliseconds(),
                    fileBoxContainer = el.options.fileContainer.querySelector('.mp-file-box-container');

                var fileBox = document.createElement('div');
                fileBox.id = "mp-file-box-" + fileId;
                fileBox.className = "mp-file-box";
                fileBox.innerHTML = `
            <i class="fa fa-times" data-role="delete-button" @click="_remove($event)" aria-hidden="true"></i>
            <span class="file-info uploading">${file.name}
                <span class="file-size"> (${el.bytesToSize(file.size)})</span>
            </span>
        `;
                fileBoxContainer.appendChild(fileBox);

                if (formData) {
                    formData.delete('file');
                    formData.delete('file_id');
                    formData.append('file', file);
                    formData.append('file_id', fileId);

                    fetch(el.options.uploadUrl, {
                        method: "POST",
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(response => response.json())
                        .then(response => {
                            var fileBox = document.querySelector(`.mp-files #mp-file-box-${fileId}`);

                            if (response.ajaxRedirect) {
                                window.location.href = response.ajaxRedirect;
                            }

                            if (response.status) {
                                fileBox.innerHTML += response.file_html;
                                fileBox.querySelector('.file-info').classList.remove('uploading');
                                el.options.fileNumber++;
                                el.options.fileContainer.querySelector('span.attachment-num').textContent = el.options.fileNumber;
                            } else {
                                fileBox.querySelector('.file-info').classList.remove('uploading');
                                fileBox.querySelector('.file-info').classList.add('error');
                                alert(response.error);
                            }
                        })
                        .catch(error => console.error('Upload error:', error));
                }
            });

            event.target.value = '';
        },

        /**
         * Submit request reply
         *
         * @param event
         * @private
         */
        _submitReply: function (event) {
            var el = this,
                requestForm = event.target.closest('form#edit_form'),
                replyLoader = document.querySelector('#request_conversation_fieldset #mp-request-reply-container .mp-rma-image-loader'),
                submitBtn = document.querySelector('button#mp-replies-submit[data-role=submit-reply]'),
                replyContent = requestForm.querySelector('textarea[name="reply[content]"]'),
                isCustomerNotified = requestForm.querySelector('input[name="reply[is_customer_notified]"]'),
                isVisibleOnFront = requestForm.querySelector('input[name="reply[is_visible_on_front]"]'),
                fileContainer = el.options.fileContainer,
                fileBoxContainer = fileContainer.querySelector('.mp-file-box-container'),
                attachmentNum = fileContainer.querySelector('span.attachment-num'),
                conversationCtn = el.options.conversationCtn.querySelector('.mp-request-conversation');

            if (el.options.fileNumber === 0 && replyContent.value.trim() === '') {
                document.querySelector('#request_conversation_fieldset .mp-rma-message.reply-message').innerHTML = this._getErrorMessage('Please enter the comments.');
            } else {
                submitBtn.disabled = true;
                replyLoader.style.display = 'block';

                var formData = new FormData(requestForm);

                fetch(el.options.saveReplyUrl, {
                    method: "POST",
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                    .then(response => response.json())
                    .then(response => {
                        if (response.ajaxRedirect) {
                            window.location.href = response.ajaxRedirect;
                        }
                        if (response.requestRedirect) {
                            window.location.href = response.requestRedirect;
                        }
                        if (response.status) {
                            replyContent.value = '';
                            if (isCustomerNotified) {
                                isCustomerNotified.checked = false;
                            }
                            if (isVisibleOnFront) {
                                isVisibleOnFront.checked = false;
                            }
                            fileBoxContainer.innerHTML = '';
                            el.options.fileNumber = 0;
                            attachmentNum.textContent = el.options.fileNumber;
                            fileContainer.style.display = 'none';
                            conversationCtn.innerHTML = response.conversation;
                            hyva.activateScripts(conversationCtn);
                            document.dispatchEvent(new Event('contentUpdated'));
                        }
                        if (el.options.replyMessage) {
                            el.options.replyMessage.innerHTML = response.message;
                        }
                    })
                    .catch(error => console.error('Error submitting reply:', error))
                    .finally(() => {
                        replyLoader.style.display = 'none';
                        submitBtn.disabled = false;
                    });
            }
        },

        /**
         * Remove reply attachment files
         */
        _remove: function (event) {
            var fileBox = event.target.parentElement,
                fileContainer = this.options.fileContainer,
                attachmentNum = fileContainer.querySelector('span.attachment-num');

            if (!fileBox.querySelector('.error')) {
                this.options.fileNumber--;
            }

            fileBox.remove();
            attachmentNum.textContent = this.options.fileNumber;

            if (!fileContainer.querySelector('.mp-file-box')) {
                fileContainer.style.display = 'none';
            }
        },

        /**
         * Load request reply
         */
        _loadReply: function (loadAll) {
            var el = this,
                requestForm = document.querySelector('form#edit_form'),
                requestId = requestForm.querySelector('#request_request_id').value,
                customerEmail = requestForm.querySelector('#mp-reply-email').value;

            el.options.conversationLoader.style.display = 'block';

            fetch(el.options.loadReplyUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    request_id: requestId === '' ? 0 : requestId,
                    load_all: loadAll,
                    customer_email: customerEmail
                })
            })
                .then(response => response.json())
                .then(response => {
                    if (response.ajaxRedirect) {
                        window.location.href = response.ajaxRedirect;
                    }
                    if (response.requestRedirect) {
                        window.location.href = response.requestRedirect;
                    }
                    el.options.conversationCtn.querySelector('.mp-request-conversation').innerHTML = response.conversation;
                    if (response.status) {
                        var event = new Event('contentUpdated');
                        el.options.conversationCtn.querySelector('.mp-request-conversation').dispatchEvent(event);
                    }
                })
                .finally(() => {
                    el.options.conversationLoader.style.display = 'none';
                });
        }
    }
}
