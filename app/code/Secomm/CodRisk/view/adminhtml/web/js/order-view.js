/**
 * Secomm_CodRisk — Order View COD Risk section (TASK-YPWH9B).
 *
 * Binds the section's toggle buttons to their form panels:
 * buttons carry data-codrisk-toggle="<panel-id>", panels start [hidden].
 * Initialized via x-magento-init on the section element.
 */
define([], function () {
    'use strict';

    return function (config, element) {
        element.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-codrisk-toggle]');

            if (!trigger) {
                return;
            }

            var panel = document.getElementById(trigger.getAttribute('data-codrisk-toggle'));

            if (!panel) {
                return;
            }

            // Accordion: opening a panel closes its siblings — two open forms on
            // one order invites submitting into the wrong one.
            var willOpen = panel.hidden;

            element.querySelectorAll('.codrisk-form-panel').forEach(function (other) {
                other.hidden = true;
            });

            if (willOpen) {
                panel.hidden = false;
            }
        });
    };
});
