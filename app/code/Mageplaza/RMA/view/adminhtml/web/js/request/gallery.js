/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license sliderConfig is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category  Mageplaza
 * @package   Mageplaza_RMA
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

define(
    [
        'jquery',
        'productGallery',
        'mageplaza/core/jquery/popup',
        'prototype'
    ], function ($, productGallery) {
        'use strict';

        $.widget(
            'mage.productGallery', productGallery, {
                /** add option type image */
                options: {
                    types: {}
                },

                _create: function () {
                    this._super();

                    /** init mp magnificPopup */
                    $('.mp-zoom-file').magnificPopup({
                        type: 'image',
                        closeOnContentClick: true
                    });
                    this.options.initialized = true;
                },

                /**
                 * Bind handler to elements
                 * @protected
                 */
                _bind: function () {
                    var events = {};

                    this._on({
                        updateImageTitle: '_updateImageTitle',
                        updateVisibility: '_updateVisibility',
                        openDialog: '_onOpenDialog',
                        addItem: '_addItem',
                        removeItem: '_removeItem',
                        setImageType: '_setImageType',
                        setPosition: '_setPosition',
                        resort: '_resort',

                        /**
                         * @param {$.Event} event
                         */
                        'mouseup [data-role=delete-button]': function (event) {
                            var $imageContainer;

                            event.preventDefault();
                            $imageContainer = $(event.currentTarget).closest(this.options.imageSelector);
                            this.element.find('[data-role=dialog]').trigger('close');
                            this.element.trigger('removeItem', $imageContainer.data('imageData'));
                        },

                        /**
                         * @param {$.Event} event
                         */
                        'mouseup [data-role=make-base-button]': function (event) {
                            var $imageContainer,
                                imageData;

                            event.preventDefault();
                            event.stopImmediatePropagation();
                            $imageContainer = $(event.currentTarget).closest(this.options.imageSelector);
                            imageData       = $imageContainer.data('imageData');
                            this.setBase(imageData);
                        }
                    });

                    this.element.sortable({
                        distance: 8,
                        items: this.options.imageSelector,
                        tolerance: 'pointer',
                        cancel: 'input, button, .uploader',
                        update: $.proxy(function () {
                            this.element.trigger('resort');
                        }, this)
                    });

                    /**
                     * @param {$.Event} event
                     */
                    events['click ' + this.options.imageSelector] = function (event) {
                        if ($(event.target).attr('data-role') !== 'delete-button') {
                            if ($(event.target).parents('.image.item').attr('data-file-type') === 'image') {
                                var imgLink = $(event.target).parents('.image.item').find('.mp-zoom-file');

                                imgLink.trigger('click');
                            } else {
                                window.location.href = mpRMAFormBefore.downloadFileUrl + '?file_info=' + $(event.target).parents('.image.item').attr('data-file-info');
                            }
                        }
                    };
                    this._on(events);
                },

                /**
                 * Add image
                 * @param {$.Event} event
                 * @param {Object} imageData
                 * @private
                 */
                _addItem: function (event, imageData) {
                    this._super(event,imageData);

                    /** init mp magnificPopup */
                    $('.mp-zoom-file').magnificPopup({
                        type: 'image',
                        closeOnContentClick: true
                    });
                }
            }
        );

        return $.mage.productGallery;
    }
);