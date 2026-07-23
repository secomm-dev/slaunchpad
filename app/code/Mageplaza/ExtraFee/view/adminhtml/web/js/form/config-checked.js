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
 * @category  Mageplaza
 * @package   Mageplaza_ExtraFee
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

'use strict';
define(
    [
        'jquery'
    ], function ($) {

        $.widget(
            'mageplaza.configChecked', {
                _create: function () {
                    this.initUseConfig();
                    this.useConfigCheckedObs();
                },
                initUseConfig: function () {
                    $('.mp-use-config:checked').each(function () {
                        var enableEl = $(this).parents('.add-after').siblings();
                        enableEl.prop('disabled', true);
                    });
                },
                useConfigCheckedObs: function () {
                    $('.mp-use-config').on('click', function () {
                        var enableEl = $(this).parents('.add-after').siblings();
                        enableEl.prop('disabled', $(this).is(':checked'));
                    });
                }
            });

        return $.mageplaza.configChecked;
    }
);
