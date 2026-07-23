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
 * @package   Mageplaza_SocialLogin
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

define(
    [
        'jquery',
        'Magento_Ui/js/modal/alert',
        'mage/translate'
    ], function ($, alert, $t) {
        'use strict';

        /**
         * @param url
         * @param windowObj
         */
        window.socialCallback = function (url, windowObj) {
            if (url !== '') {
                window.location.href = url;
            } else {
                window.location.reload(true);
            }

            windowObj.close();
        };

        return function (config, element) {
            var model = {
                initialize: function () {
                    var self = this,
                        el   = $(element);
                    el.on(
                        'click', function () {
                            if (parseInt(config.status) === 1) {
                                alert({
                                    title: $t('Are you sure to disconnect to this social channel?'),
                                    modalClass: 'disconnect-confirm',
                                    buttons: [{
                                        text: $t('Cancel'),
                                        click: function () {
                                            this.closeModal(true);
                                        }
                                    }, {
                                        text: $t('Ok'),
                                        click: function () {
                                            $.ajax(
                                                {
                                                    url: config.disconnect_url,
                                                    data: {
                                                        type: config.type
                                                    },
                                                    method: 'POST'
                                                }
                                            ).done(
                                                function () {
                                                    config.status = false;
                                                    el.find('.state').text(config.type);
                                                }
                                            );
                                            this.closeModal(true);
                                        }
                                    }]
                                });

                                $('.disconnect-confirm .modal-inner-wrap').css('width', '35%');
                            } else {
                                var processData = $.ajax(
                                    {
                                        url: config.pd_url,
                                        data: {
                                            id: config.id,
                                            type: config.type
                                        },
                                        method: 'POST'
                                    }
                                );
                                processData.done(
                                    function () {
                                        self.openPopup();
                                    }
                                );
                            }
                        }
                    );
                },

                openPopup: function () {
                    window.open(config.url, config.label, this.getPopupParams());
                },

                getPopupParams: function (w, h, l, t) {
                    this.screenX     = typeof window.screenX !== 'undefined' ? window.screenX : window.screenLeft;
                    this.screenY     = typeof window.screenY !== 'undefined' ? window.screenY : window.screenTop;
                    this.outerWidth  = typeof window.outerWidth !== 'undefined' ? window.outerWidth : document.body.clientWidth;
                    this.outerHeight = typeof window.outerHeight !== 'undefined' ? window.outerHeight : (document.body.clientHeight - 22);
                    this.width       = w ? w : 500;
                    this.height      = h ? h : 420;
                    this.left        = l ? l : parseInt(this.screenX + ((this.outerWidth - this.width) / 2), 10);
                    this.top         = t ? t : parseInt(this.screenY + ((this.outerHeight - this.height) / 2.5), 10);

                    return (
                        'width=' + this.width +
                        ',height=' + this.height +
                        ',left=' + this.left +
                        ',top=' + this.top
                    );
                }
            };
            model.initialize();

            return model;
        };
    }
);
