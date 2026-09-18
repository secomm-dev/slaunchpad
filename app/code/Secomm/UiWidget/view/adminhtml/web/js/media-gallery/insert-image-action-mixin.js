/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

define([
    'jquery'
], function ($) {
    'use strict';

    return function (insertImageAction) {
        var getTargetElement = insertImageAction.getTargetElement;

        insertImageAction.getTargetElement = function (targetElementId) {
            var target = document.getElementById(targetElementId);

            if (target && target.hasAttribute('data-secomm-ui-media-target')) {
                return $(target);
            }

            return getTargetElement.call(this, targetElementId);
        };

        return insertImageAction;
    };
});
