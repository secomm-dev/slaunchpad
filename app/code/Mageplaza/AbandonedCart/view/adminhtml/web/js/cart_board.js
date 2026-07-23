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
 * @package     Mageplaza_AbandonedCart
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

/*jshint browser:true jquery:true*/
/*global alert*/
define(
    [
        'ko',
        'jquery',
        'underscore',
        'uiComponent',
        'mage/url',
        "Magento_Ui/js/modal/alert",
        "mage/translate"
    ],
    function (ko, $, _, Component, url, alert, $t) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Mageplaza_AbandonedCart/cart_board'
            },
            params: {},
            realtime: ko.observable(),
            abandoned: ko.observable(),
            recoverable: ko.observable(),
            converted: ko.observable(),

            initialize: function () {
                this._super();
                this.initParams();
                this.initData();
                this.initPaginationApply();
                return this;
            },

            initData: function () {
                const self          = this;
                const ajaxUrl       = url.build('index/cartboard');
                const dateRangeData = $('#daterange').data();
                const params        = this.params.mpFilter || {
                    mpFilter: {
                        startDate: dateRangeData.startDate.format('Y-MM-DD'),
                        endDate: dateRangeData.endDate.format('Y-MM-DD')
                    }
                };

                if (this.params.filters) {
                    params.filters = this.params.filters;
                }

                $.ajax({
                    url: ajaxUrl,
                    data: this.params,
                    method: 'POST',
                    showLoader: true,
                    success: function (res) {
                        self.realtime(res.data.realtime);
                        self.abandoned(res.data.abandoned);
                        self.recoverable(res.data.recoverable);
                        self.converted(res.data.converted);
                        // pagination logic
                        if (res.data.pageData) {
                            const totalPage = res.data.pageData.totalPage || 1;
                            $('.pagination').css('visibility', 'visible');
                            $('.pagination #total-page').text(totalPage);
                            $('.pagination #current-page').val(res.data.pageData.currentPage || 1);

                            const page = res.data.pageData.currentPage || 1;
                            $('.pagination .action-previous').prop('disabled', page <= 1);
                            $('.pagination .action-next').prop('disabled', page >= totalPage);
                        }
                    },
                    error: function () {
                        alert({
                            title: $t('Error'),
                            content: $t('Please submit again')
                        });
                    }
                });
            },

            getRealtimeData: function () {
                return this.realtime();
            },

            getAbandonedData: function () {
                return this.abandoned();
            },

            getRecoverableData: function () {
                return this.recoverable();
            },

            getConvertedData: function () {
                return this.converted();
            },

            initParams: function () {
                const mpFilter      = this.params.mpFilter || (this.params.mpFilter = {});
                const dateRangeData = $('#daterange').data();

                mpFilter.startDate         = mpFilter.startDate || dateRangeData.startDate.format('Y-MM-DD');
                mpFilter.endDate           = mpFilter.endDate || dateRangeData.endDate.format('Y-MM-DD');
                mpFilter.store             = mpFilter.store || $('#store_switcher').val();
                mpFilter.customer_group_id = mpFilter.customer_group_id || $('.customer-group select').val();
                mpFilter.page_size         = mpFilter.page_size || $('.pagination select').val();
                mpFilter.current_page      = mpFilter.current_page || $('.pagination input').val();

                this.params = {
                    mpFilter: mpFilter
                };
            },

            initPaginationApply: function () {
                const pageSizeEle    = $('#page-size');
                const currentPageEle = $('#current-page');

                pageSizeEle.change(() => {
                    const pageSize    = pageSizeEle.children("option:selected").attr('value');
                    const currentPage = parseInt(currentPageEle.val());

                    this.params.mpFilter              = this.params.mpFilter || {};
                    this.params.mpFilter.page_size    = pageSize;
                    this.params.mpFilter.current_page = currentPage;
                    this.initData();
                });

                $('.action-previous').click(() => {
                    const currentPage = parseInt(currentPageEle.val());
                    if (currentPage > 1) {
                        currentPageEle.val(currentPage - 1).change();
                    }
                });

                $('.action-next').click(() => {
                    const currentPage = parseInt(currentPageEle.val());
                    const totalPages  = pageSizeEle.children("option:selected").attr('value');
                    if (currentPage < totalPages) {
                        currentPageEle.val(currentPage + 1).change();
                    }
                });

                currentPageEle.change(() => {
                    const totalPage = pageSizeEle.children("option:selected").attr('value');
                    let currentPage = parseInt(currentPageEle.val());

                    this.params.mpFilter              = this.params.mpFilter || {};
                    this.params.mpFilter.page_size    = totalPage;
                    this.params.mpFilter.current_page = currentPage;
                    this.initData();
                });
            }
        });
    }
);
