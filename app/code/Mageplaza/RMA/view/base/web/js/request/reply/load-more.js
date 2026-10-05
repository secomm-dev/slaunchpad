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
], function ($, rmaReply) {
    'use strict';

    $.widget('mageplaza.rmaReplyLoadMore', rmaReply, {
        options: {
            loadReplyUrl: {}
        },

        /**
         * @inheritDoc
         */
        _create: function () {
            this._on(this.element, {
                'click button[data-role=load-all]': function () {
                    this._loadReply(1);
                }
            });
        },
    });

    return $.mageplaza.rmaReplyLoadMore;
});