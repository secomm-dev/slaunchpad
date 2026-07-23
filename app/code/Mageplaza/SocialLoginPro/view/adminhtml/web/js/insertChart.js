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
 * @package   Mageplaza_SocialShare
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

define(
    [
        'jquery',
        'uiComponent',
        'chartBundle'
    ], function ($, component) {
        'use strict';
        return component.extend(
            {
                initialize: function (config) {
                    var data     = JSON.parse(config.data);
                    var percent  = [],
                        provider = [];
                    _.each(data, function (record, index) {
                        percent.push(record.percent);
                        provider.push(record.provider);
                    });
                    var socialLoginChart = $("#social_login_chart");

                    window.qtyChart = new Chart(socialLoginChart, {
                        type: 'pie',
                        data: {
                            labels: provider,
                            datasets: [
                                {
                                    data: percent,
                                    fill: true,
                                    backgroundColor: [
                                        '#dd4b39',
                                        '#6f42c1',
                                        '#f86c6b',
                                        '#f8cb00',
                                        '#4dbd74',
                                        '#73818f'
                                    ],
                                    borderWidth: 1
                                }
                            ]
                        },
                        options: {
                            legend: {
                                display: true,
                                position: 'bottom'
                            },
                            tooltips: {
                                mode: 'index',
                                callbacks: {}
                            }
                        }
                    });
                }
            }
        );
    }
);