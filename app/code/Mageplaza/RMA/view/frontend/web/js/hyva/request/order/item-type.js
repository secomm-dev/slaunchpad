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

function initItemType(canReturnAllProducts, element) {
    return {
        /**
         * Initialize the widget
         */
        init: function() {
            this.options.eachItem = document.querySelector('fieldset.mp-items-type .mp-field-items'),
            this.options.allItem = document.querySelector('fieldset.mp-items-type .mp-field-all-items'),
            this.options.canReturnAllProducts = canReturnAllProducts,
            this.options.submitBtn = document.querySelector('button[data-role=request-submit]'),
            this.options.selectInputs = document.querySelectorAll('table#mp-RMA-return-items input[data-role=enable-item]')

            this.options.submitBtn.disabled = !this.options.canReturnAllProducts;
            this.element = element;
            this.element.addEventListener('change', (event) => {
                if (event.target.matches('select[data-role=return-type]')) {
                    this.changeReturnType(event.target.value);
                }
            });
        },

        /**
         * Change request return type
         * @param {string} type
         */
        changeReturnType: function(type) {
            let isSelected = false;

            if (type === '2') {
                this.options.eachItem.style.display = 'block';
                this.options.allItem.style.display = 'none';

                this.options.allItem.querySelectorAll('select, input, textarea').forEach(input => {
                    input.disabled = true;
                });

                this.options.selectInputs.forEach(input => {
                    if (input.checked) {
                        isSelected = true;
                    }
                });

                this.options.submitBtn.disabled = !isSelected;
            } else {
                this.options.eachItem.style.display = 'none';
                this.options.allItem.style.display = 'block';

                this.options.allItem.querySelectorAll('select, input, textarea').forEach(input => {
                    input.disabled = false;
                });

                this.options.submitBtn.disabled = false;
            }
        }
    }
}
