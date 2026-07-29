/**
 * based on Magento_Variable/js/grid/columns/radioselect
 */

define([
    'underscore',
    'mage/translate',
    'Magento_Ui/js/grid/columns/column',
    'jquery'
], function (_, $t, Column, jQuery) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'Mirasvit_SeoContent/grid/cells/radioselect',
            draggable: false,
            sortable: false,
            selectedVariableCode: null,
            selectedVariableType: null
        },

        /**
         * Calls 'initObservable' of parent
         *
         * @returns {Object} Chainable.
         */
        initObservable: function () {
            this._super().observe(['selectedVariableCode']);

            return this;
        },

        /**
         * Remove disable class from Insert Variable button after Variable has been chosen.
         *
         * @return {Boolean}
         */
        selectVariable: function () {
            if (jQuery('#insert_variable').hasClass('disabled')) {
                jQuery('#insert_variable').removeClass('disabled');
            }

            return true;
        }
    });
});
