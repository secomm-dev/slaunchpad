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
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */
define([
    "jquery",
    "Magento_Ui/js/modal/alert",
    "mage/translate",
    "jquery/ui"
], function ($, alert, $t) {
    "use strict";

    $.widget('mageplaza.testEmail', {
        options: {
            ajaxUrl: '',
            testEmail: '#smtp_configuration_option_test_email_sent',
            fromEmailElem: '#smtp_configuration_option_test_email_from',
            hostElem: '#smtp_configuration_option_host',
            portElem: '#smtp_configuration_option_port',
            authenticationElem: '#smtp_configuration_option_authentication',
            protocolElem: '#smtp_configuration_option_protocol',
            usernameElem: '#smtp_configuration_option_username',
            passwordElem: '#smtp_configuration_option_password',
            rerutnPathElem: '#smtp_configuration_option_return_path_email',
            oauthTenantIdElem: '#smtp_configuration_option_oauth_tenant_id',
            oauthClientIdElem: '#smtp_configuration_option_oauth_client_id',
            oauthClientSecretElem: '#smtp_configuration_option_oauth_client_secret',
            oauthScopeElem: '#smtp_configuration_option_oauth_scope'
        },
        _create: function () {
            var self = this;

            $(this.options.testEmail).click(function (e) {
                e.preventDefault();
                if (self.element.val()) {
                    self._ajaxSubmit();
                }
            });
        },

        _ajaxSubmit: function () {
            var data = {
                from: $(this.options.fromEmailElem).val(),
                to: this.element.val(),
                host: $(this.options.hostElem).val(),
                port: $(this.options.portElem).val(),
                authentication: $(this.options.authenticationElem).val(),
                protocol: $(this.options.protocolElem).val(),
                username: $(this.options.usernameElem).val(),
                password: $(this.options.passwordElem).val(),
                returnpath: $(this.options.rerutnPathElem).val()
            };

            // Add OAuth2 fields if present
            if ($(this.options.oauthTenantIdElem).length) {
                data.oauth_tenant_id = $(this.options.oauthTenantIdElem).val();
            }
            if ($(this.options.oauthClientIdElem).length) {
                data.oauth_client_id = $(this.options.oauthClientIdElem).val();
            }
            if ($(this.options.oauthClientSecretElem).length) {
                data.oauth_client_secret = $(this.options.oauthClientSecretElem).val();
            }
            if ($(this.options.oauthScopeElem).length) {
                data.oauth_scope = $(this.options.oauthScopeElem).val();
            }

            $.ajax({
                url: this.options.ajaxUrl,
                data: data,
                dataType: 'json',
                showLoader: true,
                success: function (result) {
                    alert({
                        title: result.status ? $t('Success') : $t('Error'),
                        content: result.content
                    });
                }
            });
        }
    });

    return $.mageplaza.testEmail;
});
