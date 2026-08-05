define([
    'jquery',
    'jquery-ui-modules/widget'
], function ($) {
    'use strict';

    $.widget('magezon.mgzTabs', {
        _create: function () {
            var self = this;

            $('.mgz-tabs-tab-content:not(.mgz-active) .owl-carousel').addClass('mgz-carousel-hidden');

            var $tabsList    = this.element.children('.mgz-tabs-nav');
            var $tabsContent = this.element.children('.mgz-tabs-content');
            $tabsList.children('.mgz-tabs-tab-title').each(function(index, el) {
                var outerHTML = $(this)[0].outerHTML;
                var anchor    = $(this).children('a');
                var targetId  = $(this).children('a').data('id');
                if (targetId) {
                    self.element.find(targetId).before(outerHTML);
                }
            });

            var activeTab = function(anchor) {
                var $title   = anchor.closest('.mgz-tabs-tab-title'),
                isOpen   = $title.hasClass('mgz-active'),
                parentId = $title.attr('data-id'),
                targetId = anchor.data('id') ? anchor.data('id') : anchor.attr('href'),
                $target   = self.element.find(targetId);
                /* --- collapse -------------------------------------------------- */
                if(isOpen) {
                    $title.removeClass('mgz-active');
                    if(parentId) { self.element.find('.' + parentId). removeClass('mgz-active'); }
                    $target.removeClass('mgz-active'). attr('style' , '');
                    $(self.element). parents('.mgz-element'). trigger('mgz:change');
                    return true;                     // stop here – nothing to open
                }
                /* --- open (Original logic) --------------------------- */
                $tabsList.children (). removeClass('mgz-active');
                $tabsContent.children (). removeClass('mgz-active');

                $title.addClass('mgz-active');
                if(parentId) { self.element.find('.' + parentId). addClass('mgz-active'); }
                $target.addClass('mgz-active');
                $(self.element). parents('.mgz-element'). trigger('mgz:change');
                setTimeout(function () {
                    $target.find('.owl-carousel.mgz-carousel-hidden')
                            .removeClass('mgz-carousel-hidden');
                }, 500);

                return true;
            }

            if (this.options.hover_active) {
                $tabsList.children().hover(function(e) {
                    activeTab($(this).children('a'));
                });
            }

            $tabsList.children().click(function(e) {
                if ($(this).children('a').attr('href').indexOf('#') !== -1) {
                    e.preventDefault();
                    activeTab($(this).children('a'));
                    return false;
                }
            });

            $tabsContent.children('.mgz-tabs-tab-title').click(function(e) {
                e.preventDefault();
                activeTab($(this).children('a'));
                return false;
            });
        }
    });

    return $.magezon.mgzTabs;
});