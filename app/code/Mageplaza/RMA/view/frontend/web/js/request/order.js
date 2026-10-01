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
    'mpRMA/request'
], function (_, $, mpRMARequest) {
    'use strict';

    $.widget('mageplaza.rmaOrder', mpRMARequest, {
        options: {
            fileMessage: $('form#mp-edit-rma-form .mp-rma-message.files-message'),
            fileLoader: $('form#mp-edit-rma-form .mp-image .loader'),
            fileWrapper: $('form#mp-edit-rma-form .request-image-wrapper i'),
            itemContainer : $('form#mp-edit-rma-form .mp-rma-information-container'),
            itemLoader: $('form#mp-edit-rma-form .mp-rma-image-loader.order-loader'),
            orderIdVal: $('form#mp-edit-rma-form .mp-field-order-id select[name="order[order_id]"]').val(),
            requestOrderID: $('form#mp-edit-rma-form input[name="request[order_id]"]'),
            requestCuzEmail: $('form#mp-edit-rma-form input[name="request[customer_email]"]'),
            requestStoreId: $('form#mp-edit-rma-form input[name="request[store_id]"]'),
            requestOrderIncrementID: $('form#mp-edit-rma-form input[name="request[order_increment_id]"]'),
            orderLastName: $('form#mp-edit-rma-form input[name="order[bill_lastname]"]'),
            orderEmail: $('form#mp-edit-rma-form input[name="order[email]"]'),
            uploadUrl: {},
            loadOrderInfoUrl: {}
        },

        /**
         * @inheritDoc
         */
        _create: function () {
            this._initOrderInformation();
            this._bind();
        },

        /**
         * Bind handler to elements
         * @protected
         */
        _bind: function () {
            this._on(this.element, {
                'change input[data-role=upload-files]': function (event) {
                    this._uploadFile(event);
                },

                'change select[data-role=load-order]': function (event) {
                    this.options.orderIdVal = $(event.target).val();
                    this._initOrderInformation();
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

            _.each(files, function (file) {
                var d      = new Date(),
                    fileId = d.getTime() + '_' + d.getMilliseconds();

                el.options.fileLoader.show();
                el.options.fileWrapper.hide();
                el.options.fileMessage.html('');
                if (formData) {
                    formData.delete('file');
                    formData.delete('position');
                    formData.delete('file_id');
                    formData.append('file', file);
                    formData.append('position', $('#mp-image-place-holder').prev().find('input.position').val());
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
                                    el.options.fileLoader.hide();
                                    el.options.fileWrapper.show();
                                    if (response.status) {
                                        $('#mp-image-place-holder').before($(response.request_files).html());
                                    } else {
                                        el.options.fileMessage.html(el._getErrorMessage(response.errorSize));
                                    }
                                }
                        }
                    );
                }
            });
            $(event.target).val('');
        },

        /**
         * Init rma order information function
         */
        _initOrderInformation: function () {
            var el = this;

            el.options.itemLoader.show();
            $.ajax({
                type: "POST",
                url: el.options.loadOrderInfoUrl,
                data: {order_id: el.options.orderIdVal},
                success: function (response) {
                    var itemList = el.options.itemContainer.find('div.control');

                    if (response.status) {
                        el.options.requestOrderID.val(el.options.orderIdVal);
                        el.options.requestCuzEmail.val(response.customer_email);
                        el.options.requestStoreId.val(response.store_id);
                        el.options.orderLastName.val(response.billing_lastname);
                        el.options.orderEmail.val(response.customer_email);
                        el.options.requestOrderIncrementID.val(response.order_increment_id);
                        itemList.html(response.request_products);
                        itemList.trigger('contentUpdated');
                    } else {
                        itemList.html(response.error_message);
                    }
                },
                complete: function () {
                    el.options.itemLoader.hide();
                }
            });
        }
    });

    return $.mageplaza.rmaOrder;
});