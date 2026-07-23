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
 * @package     Mageplaza_OscUltimate
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

var config = {};
if (typeof window.oscRoute !== 'undefined' && window.location.href.indexOf(window.oscRoute) !== -1) {
    config = {
        map: {
            '*': {
                'Mageplaza_Osc/template/1column.html': 'Mageplaza_OscUltimate/template/1column.html',
                'Mageplaza_Osc/template/2columns.html': 'Mageplaza_OscUltimate/template/2columns.html',
                'Mageplaza_Osc/template/2columns-floating.html': 'Mageplaza_OscUltimate/template/2columns-floating.html',
                'Mageplaza_Osc/template/3columns.html': 'Mageplaza_OscUltimate/template/3columns.html',
                'Mageplaza_Osc/template/3columns-colspan.html': 'Mageplaza_OscUltimate/template/3columns-colspan.html',
            }
        },
    }

}
