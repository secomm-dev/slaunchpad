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
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

define(['jquery', 'ko'], function ($, ko) {
    "use strict";

    return {
        ruleConfig: ko.observable([]),
        shippingRuleConfig: ko.observable([]),
        billingRuleConfig: ko.observable([]),
        selectedOptions: ko.observable([]),
        shippingSelectedOptions: ko.observable([]),
        billingSelectedOptions: ko.observable([]),
        ruleMultiShipping: ko.observable([]),
        selectedOptionsMultiShipping: ko.observable([]),
        ruleVirtualMultiShipping: ko.observable([]),
        selectedOptionsVirtualMultiShipping: ko.observable([])
    }
});
