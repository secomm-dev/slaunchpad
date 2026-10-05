/**
 * Secomm_CodRisk — Effective From/To datetime pickers (TASK-YPWH9B).
 *
 * mage/calendar returns a BUNDLE object ({dateRange, calendar}) — it cannot be
 * applied through data-mage-init directly; the widget must be invoked via
 * $(element).calendar(options) from a require context (same as core reports grid).
 *
 * Default options mirror the core datepicker binding (datepicker.js):
 * picker opens on focus — no calendar icon is part of the standard admin field.
 *
 * Initialized via data-mage-init on the Risk Lists <form>.
 */
define(['jquery', 'mage/calendar'], function ($) {
    'use strict';

    return function (config, element) {
        $(element).find('.codrisk-effective-date').calendar({
            showsTime: true,
            dateFormat: 'yy-mm-dd',
            timeFormat: 'HH:mm:ss',
            showSecond: true
        });
    };
});