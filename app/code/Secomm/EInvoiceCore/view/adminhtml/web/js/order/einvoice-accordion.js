/**
 * Collapsible accordion sections on the admin order EInvoice tab.
 */
define([], function () {
    'use strict';

    var storagePrefix = 'secomm_einvoice_accordion_order_';

    /**
     * @param {string} orderId
     * @returns {Record<string, boolean>|null}
     */
    function loadState(orderId) {
        if (!orderId || !window.localStorage) {
            return null;
        }

        try {
            var raw = window.localStorage.getItem(storagePrefix + orderId);

            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    /**
     * @param {string} orderId
     * @param {Record<string, boolean>} state
     */
    function saveState(orderId, state) {
        if (!orderId || !window.localStorage) {
            return;
        }

        try {
            window.localStorage.setItem(storagePrefix + orderId, JSON.stringify(state));
        } catch (e) {
            // Ignore quota / private mode errors.
        }
    }

    /**
     * @param {HTMLElement} item
     * @param {boolean} open
     */
    function setItemOpen(item, open) {
        var trigger = item.querySelector('.secomm-einvoice__accordion-trigger');
        var panel = item.querySelector('.secomm-einvoice__accordion-panel');

        if (!trigger || !panel) {
            return;
        }

        item.classList.toggle('secomm-einvoice__accordion-item--open', open);
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        panel.hidden = !open;
    }

    /**
     * @param {HTMLElement} root
     * @returns {Record<string, boolean>}
     */
    function collectState(root) {
        /** @type {Record<string, boolean>} */
        var state = {};

        root.querySelectorAll('[data-accordion-section]').forEach(function (item) {
            var section = item.getAttribute('data-accordion-section');

            if (section) {
                state[section] = item.classList.contains('secomm-einvoice__accordion-item--open');
            }
        });

        return state;
    }

    return function (config, element) {
        var orderId = config.orderId ? String(config.orderId) : '';
        var defaults = config.defaults || {};
        var saved = loadState(orderId);
        var state = saved || defaults;

        element.querySelectorAll('[data-accordion-section]').forEach(function (item) {
            var section = item.getAttribute('data-accordion-section');
            var open = section && Object.prototype.hasOwnProperty.call(state, section)
                ? !!state[section]
                : false;

            setItemOpen(item, open);

            var trigger = item.querySelector('.secomm-einvoice__accordion-trigger');

            if (!trigger) {
                return;
            }

            trigger.addEventListener('click', function () {
                var isOpen = item.classList.contains('secomm-einvoice__accordion-item--open');

                setItemOpen(item, !isOpen);
                saveState(orderId, collectState(element));
            });
        });
    };
});
