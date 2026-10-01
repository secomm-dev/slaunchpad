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

function initForm() {
    return {
        options: {
            zipCode: '#mprma-zip', // Search by zip code.
            emailAddress: '#mprma-email', // Search by email address.
            searchType: '#quick-search-type-id' // Search element used for choosing between the two.
        },

        /**
         *
         * Initialize the widget
         */
        init: function() {
            const searchTypeElement = document.querySelector(this.options.searchType);
            if (searchTypeElement) {
                searchTypeElement.addEventListener('change', (e) => this.showIdentifyBlock(e));
                this.triggerChange(searchTypeElement); // Trigger change event on load
            }
        },

        /**
         * Show either the search by zip code option or the search by email address option.
         * @param {Event} e - Change event. Event target value is either 'zip' or 'email'.
         */
        showIdentifyBlock: function(e) {
            const value = e.target.value;
            const zipCodeElement = document.querySelector(this.options.zipCode);
            const emailAddressElement = document.querySelector(this.options.emailAddress);

            if (zipCodeElement) {
                zipCodeElement.style.display = value === 'zip' ? 'block' : 'none';
            }

            if (emailAddressElement) {
                emailAddressElement.style.display = value === 'email' ? 'block' : 'none';
            }
        },

        /**
         * Trigger a change event programmatically.
         * @param {HTMLElement} element - The element to trigger change event on.
         */
        triggerChange: function(element) {
            const event = new Event('change', {
                bubbles: true,
                cancelable: true
            });
            element.dispatchEvent(event);
        },

        myFormSubmit(event) {
            event.preventDefault();

            this.validate()
                .then(() => {
                    event.target.submit()
                })
                .catch((invalid) => {
                    if (invalid.length > 0) {
                        invalid[0].focus();
                    }
                });
        }
    }
}
