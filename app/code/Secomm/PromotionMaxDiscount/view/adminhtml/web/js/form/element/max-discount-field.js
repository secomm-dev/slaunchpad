/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

/**
 * Maximum Discount Amount input (TASK-67GGPR / SPEC-FEAT-JKZM68 §6).
 *
 * Visible and enabled only while the rule's Apply (simple_action) is
 * "Percent of product price discount" — mirrors the switch pattern of
 * Magento_SalesRule's apply_to_shipping field. The import delivers the
 * current simple_action on initial load too, so an existing rule saved with
 * another action renders the field hidden/disabled while keeping its value
 * in the form data. Disabled UI components are not submitted, so switching
 * the action never sends a stale value; the runtime collector guard
 * (TASK-5H8WKE) stays independent of this UI.
 */
define([
    'Magento_Ui/js/form/element/abstract'
], function (Abstract) {
    'use strict';

    return Abstract.extend({
        defaults: {
            imports: {
                switchBySimpleAction: '${ $.parentName }.simple_action:value'
            }
        },

        /**
         * Toggle visibility/enabled state by the selected simple action.
         *
         * @param {String} action
         */
        switchBySimpleAction: function (action) {
            var applies = action === 'by_percent';

            this.visible(applies);
            this.disabled(!applies);
        }
    });
});
