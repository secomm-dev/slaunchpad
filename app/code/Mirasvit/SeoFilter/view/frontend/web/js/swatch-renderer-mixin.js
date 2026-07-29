define([
    'jquery',
    'underscore'
], function ($, _) {
    'use strict';

    return function (SwatchRenderer) {
        $.widget('mage.SwatchRenderer', SwatchRenderer, {
            /**
             * Override _RenderControls to preselect swatches based on SEO filter params.
             *
             * @private
             */
            _RenderControls: function () {
                this._super();
                this._EmulateActiveFilterParams();
            },

            /**
             * Preselect swatches based on active filter params from PHP.
             *
             * @private
             */
            _EmulateActiveFilterParams: function () {
                var activeParams = this.options.jsonConfig.activeFilterParams;

                if (!activeParams || _.isEmpty(activeParams)) {
                    return;
                }

                $.each(activeParams, $.proxy(function (attributeCode, optionId) {
                    var elem = this.element.find('.' + this.options.classes.attributeClass +
                            '[data-attribute-code="' + attributeCode + '"] [data-option-id="' + optionId + '"]'),
                        parentInput;

                    if (elem.length === 0 || elem.hasClass('selected')) {
                        return;
                    }

                    parentInput = elem.parent();

                    if (parentInput.hasClass(this.options.classes.selectClass)) {
                        parentInput.val(optionId);
                        parentInput.trigger('change');
                    } else {
                        elem.trigger('click');
                    }
                }, this));
            }
        });

        return $.mage.SwatchRenderer;
    };
});
