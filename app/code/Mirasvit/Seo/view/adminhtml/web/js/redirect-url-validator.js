define(['jquery', 'Magento_Ui/js/lib/validation/validator', 'mage/translate'], function ($, validator, $t) {
    'use strict';

    validator.addRule(
        'validate-redirect-url-different',
        function (value) {
            var requestUrl = $('input[name="url_from"]').val();

            return !requestUrl || value.trim() !== requestUrl.trim();
        },
        $t('Target URL must be different from Request URL.')
    );
});
