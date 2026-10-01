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

function initItems() {
    return {
        init: function () {
            this.options.selectInputs = document.querySelectorAll('table#mp-RMA-return-items input[data-role=enable-item]');
            this._bind();
        },

        /**
         * Bind handler to elements
         * @protected
         */
        _bind: function() {
            this.element.addEventListener('change', (event) => {
                if (event.target.matches('input[data-role=enable-item]')) {
                    this._enableItem(event);
                }
            });

            this.element.addEventListener('keyup', (event) => {
                if (event.target.matches('input[data-role=change-qty]')) {
                    this._changeQty(event);
                }
            });
        },

        /**
         * Enable or disable item row
         * @param {Event} event
         * @private
         */
        _enableItem: function(event) {
            const itemId = event.target.getAttribute('data-item-id');
            const selectedRow = event.target.closest('tr');
            const additionalRow = document.querySelector('tr#mp-items-' + itemId + '-additional');
            const submitBtn = document.querySelector('button[data-role=request-submit]');

            const isSelected = Array.from(this.options.selectInputs).some(input => input.checked);

            selectedRow.querySelectorAll('input, textarea, select').forEach((el) => {
                if (el.type !== 'checkbox') {
                    el.disabled = event.target.value !== '1';
                }
            });

            if (additionalRow) {
                additionalRow.querySelectorAll('input, textarea, select').forEach((el) => {
                    el.disabled = event.target.value !== '1';
                });
                additionalRow.style.display = event.target.value === '1' ? 'contents' : 'none';
            }

            if (submitBtn) {
                submitBtn.disabled = !isSelected;
            }
        },

        /**
         * Update quantity and calculate the total amount
         * @param {Event} event
         * @private
         */
        _changeQty: function(event) {
            const selectedRow = event.target.closest('tr');
            const qty = parseFloat(event.target.value) || 0;
            const priceInput = selectedRow.querySelector('.col-price input');
            const price = parseFloat(priceInput ? priceInput.value : 0) || 0;

            const amountInput = selectedRow.querySelector('.mp-amount-return-value');
            if (amountInput) {
                amountInput.value = (qty * price).toFixed(2);
            }
        }
    }
}
