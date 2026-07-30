define([
    'jquery',
    'Magento_Ui/js/modal/alert',
    'domReady'
], function ($, modalConfirm) {
    'use strict';

    $.widget('mage.mstDashSeparator', {
        options: {
            contentSelector: '#mst_dash_separator_message',
            separatorFieldSelector: '#mst_seo_filter_general_name_separator'
        },

        _create: function () {
            $(this.options.separatorFieldSelector).on('change', function (e) {
                var separatorFieldValue = $(this.options.separatorFieldSelector).val();
                if (separatorFieldValue == "-") {
                    modalConfirm({
                        title: $.mage.__('Warning'),
                        content: $(this.options.contentSelector).html(),
                    });
                }
            }.bind(this));

        }

    });

    return $.mage.mstDashSeparator;
});