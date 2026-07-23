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
 * @package   Mageplaza_SocialLoginPro
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

define(
    [
        'jquery',
        'underscore',
        'ko',
        'uiComponent',
        'Magento_Ui/js/model/messageList',
        'Magento_Ui/js/modal/alert',
        'mage/translate',
        'socialProvider'
    ], function ($, _, ko, Component, messageList, alert, $t, socialProvider) {
        'use strict';

        var arraySocial, urlAjax;
        var changeManager = ko.observable();

        ko.bindingHandlers.socialButton = {
            init: function (element, valueAccessor, allBindings) {
                if (!allBindings.get('manager')) {
                    var config = {
                        url: allBindings.get('url'),
                        label: allBindings.get('label')
                    };
                    socialProvider(config, element);
                } else {
                    $(element).on(
                        'click', function () {
                            var parameters = allBindings.get('label'),
                                ajaxUrl    = allBindings.get('ajaxUrl');
                            alert({
                                title: $t('Are you sure to disconnect to this social channel?'),
                                modalClass: 'disconnect-confirm',
                                buttons: [
                                    {
                                        text: $t('OK'),
                                        class: 'action primary accept',
                                        click: function () {
                                            return $.ajax(
                                                {
                                                    url: ajaxUrl,
                                                    type: 'POST',
                                                    data: {
                                                        type: parameters
                                                    }
                                                }
                                            ).done(
                                                function (response) {
                                                    if (response.success) {
                                                        window.location.reload(true);
                                                    } else {
                                                        showError($t('Ajax is error. Please try again!'));
                                                    }
                                                }
                                            ).fail(
                                                function () {
                                                    showError($t('Ajax is error. Please try again!'));
                                                }
                                            );
                                        }
                                    },
                                    {
                                        text: $t('Cancel'),
                                        class: 'action',
                                        click: function () {
                                            this.closeModal(true);
                                        }
                                    }]
                            });

                            $('.disconnect-confirm .modal-inner-wrap').css('width', '35%');
                        }
                    );
                }

                /**
                 *
                 * @param text
                 */
                function showError (text) {
                    var message        = [];
                    message['message'] = text;
                    messageList.addErrorMessage(message);
                }
            }
        };

        return Component.extend(
            {
                defaults: {
                    template: 'Mageplaza_SocialLoginPro/button'
                },
                socials: ko.observable(),

                /**
                 *
                 * @returns {exports}
                 */
                initialize: function () {
                    this._super();
                    arraySocial = this;
                    urlAjax     = this.urlSocial;
                    return this;
                },

                /**
                 *
                 * @returns {exports}
                 */
                initObservable: function () {
                    var self = this;
                    this._super();

                    var arraySocial = [];
                    $.each(
                        this.availableSocials, function (key, social) {
                            arraySocial.push(social);
                        }
                    );
                    self.socials(arraySocial);

                    changeManager.subscribe(
                        function (newValue) {
                            var array = [];
                            $.each(
                                self.socials(), function (key, social) {
                                    if (newValue.toLowerCase() === social.label.toLowerCase()) {
                                        array.push(
                                            {
                                                manager: !social.manager,
                                                label: social.label,
                                                login_url: social.login_url,
                                                connected: social.connected
                                            }
                                        );
                                    } else {
                                        array.push(social);
                                    }
                                }
                            );
                            self.socials(array);
                        }
                    );
                    return this;
                },
                /**
                 *
                 * @returns {boolean}
                 */
                isActive: function () {
                    return true;
                }
            }
        );
    }
);
