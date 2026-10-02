/**
 * Secomm_CodRisk — Phone Inspector search form (TASK-YPWH9B).
 *
 * - Only digits + one optional leading "+" may be typed/pasted.
 * - Invalid VN numbers disable the submit button and show the red note.
 *
 * JS mirror of Secomm\CodRisk\Model\Phone\PhoneNormalizer — keep in sync
 * (mobile 9 digits starting 3/5/7/8/9, landline 9-10 digits starting 2).
 * Initialized via data-mage-init on the search <form>.
 */
define([], function () {
    'use strict';

    return function (config, element) {
        var input = element.querySelector('[name="phone"]');
        var button = element.querySelector('[type="submit"]');
        var note = element.querySelector('.codrisk-phone-note');

        function isValidVnPhone(value) {
            var digits = (value || '').replace(/\D+/g, '');

            if (digits.length < 7) {
                return false;
            }

            var subscriber;

            if (digits.charAt(0) === '0') {
                subscriber = digits.substring(1);
            } else if (digits.substring(0, 2) === '84') {
                subscriber = digits.substring(2);
            } else {
                return false;
            }

            var first = subscriber.charAt(0);

            return (subscriber.length === 9 && (first === '2' || '35789'.indexOf(first) !== -1)) ||
                   (subscriber.length === 10 && first === '2');
        }

        function refresh() {
            var valid = isValidVnPhone(input.value);

            button.disabled = !valid;

            if (note) {
                note.classList.toggle('visible', !valid && input.value !== '');
            }
        }

        input.addEventListener('input', function () {
            // UX: only digits, optional single leading "+" — the server
            // normalizer stays the authoritative validator.
            var cleaned = input.value.replace(/[^0-9+]/g, '').replace(/(?!^)\+/g, '');

            if (cleaned !== input.value) {
                input.value = cleaned;
            }
            refresh();
        });

        // Enter key in the input bypasses a disabled submit button — guard it.
        element.addEventListener('submit', function (event) {
            if (button.disabled) {
                event.preventDefault();
            }
        });

        refresh();
    };
});
