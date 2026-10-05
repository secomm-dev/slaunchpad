/**
 * Activate the EInvoice tab when redirected with active_tab=order_einvoice.
 *
 * Magento admin tabs pass the active tab id to the widget, but duplicate element ids
 * on the tab list item and anchor prevent jQuery UI from resolving the index.
 */
define(['jquery'], function ($) {
    'use strict';

    var TAB_NAME = 'order_einvoice';
    var TABS_SELECTOR = '#sales_order_view_tabs';

    /**
     * @returns {string|null}
     */
    function getActiveTabFromUrl() {
        var pathMatch = window.location.pathname.match(/\/active_tab\/([^/]+)/);

        if (pathMatch) {
            return decodeURIComponent(pathMatch[1]);
        }

        return new URLSearchParams(window.location.search).get('active_tab');
    }

    /**
     * @returns {void}
     */
    function activateEinvoiceTab() {
        var $tabs = $(TABS_SELECTOR);

        if (!$tabs.length) {
            return;
        }

        var $anchor = $tabs.find('a.admin__page-nav-link[name="' + TAB_NAME + '"]');

        if (!$anchor.length) {
            return;
        }

        if ($tabs.data('mageTabs')) {
            var anchors = $tabs.find('.admin__page-nav-items > li > a.admin__page-nav-link');
            var index = anchors.index($anchor[0]);

            if (index >= 0) {
                $tabs.tabs('option', 'active', index);

                return;
            }
        }

        $anchor.trigger('click');
    }

    return function () {
        if (getActiveTabFromUrl() !== TAB_NAME) {
            return;
        }

        var attempts = 0;
        var timer = window.setInterval(function () {
            attempts += 1;

            if ($(TABS_SELECTOR).data('mageTabs') || attempts >= 20) {
                window.clearInterval(timer);
                activateEinvoiceTab();
            }
        }, 50);
    };
});
