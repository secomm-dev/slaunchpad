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
    'mpRMA/reply'
], function ($, mpRMAReply) {
    'use strict';

    $.widget('mageplaza.rmaReplyDelete', mpRMAReply, {
        options: {
            deleteReplyUrl: {}
        },

        /**
         * @inheritDoc
         */
        _create: function () {
            var el = this;

            this._on(this.element, {
                'click i[data-role=delete-reply]': function (event) {
                    var replyId = $(event.target).attr('data-reply-id');

                    el.options.conversationLoader.show();
                    $.ajax({
                        type: "POST",
                        url: el.options.deleteReplyUrl,
                        data: {
                            reply_id: replyId === '' ? 0 : replyId
                        },
                        success: function (response) {
                            if (response.ajaxRedirect) {
                                window.location.href = response.ajaxRedirect;
                            }
                            if (response.status) {
                                el.options.replyMessage.html(response.message);
                                el.options.conversationLoader.hide();
                            }
                        },
                        complete: function () {
                            el._loadReply(0);
                        }
                    });
                }
            });
        }
    });

    return $.mageplaza.rmaReplyDelete;
});