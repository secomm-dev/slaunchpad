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
 * @package     Mageplaza_ThankYouPage
 * @copyright   Copyright (c) Mageplaza (http://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/translate',
    'Mageplaza_ThankYouPage/js/lib/lib/codemirror',
    'Mageplaza_ThankYouPage/js/lib/addon/display/autorefresh',
    'Mageplaza_ThankYouPage/js/lib/addon/mode/overlay',
    'Mageplaza_ThankYouPage/js/lib/addon/selection/active-line',
    'Mageplaza_ThankYouPage/js/lib/mode/css/css'
], function ($, modal, $t, CodeMirror) {
    "use strict";
    $.widget('mageplaza.thankyoupage', {
        codeMirror: [],
        variables: null,
        textarea: [
            'mpthankyoupage_description',
            'mpthankyoupage_custom_html',
            'mpthankyoupage_custom_css',
            'mpthankyoupage_coupon_label',
            'mpthankyoupage_hyva_custom_html',
            'mpthankyoupage_hyva_custom_css'
        ],
        editor: null,

        /**
         * This method constructs a new widget.
         * @private
         */
        _create: function () {
            this.ImgUrls        = JSON.parse(this.options.ImgUrls);
            this.styleDropdown  = $('#mpthankyoupage_style');
            this.VariablesPopup = $('#insert-variable-popup');
            this.modifiersData  = this.options.modifiersData;

            this.initCodeMirror();
            this.initObserve();
            this.initPopup();
            this.initDraggable();
        },

        initObserve: function () {
            this.loadTemplate();
            this.changeImageUrl();
            this.openVariableChooser();
            this.insertVariable();
            this.rowSelectObs();
            this.inputObs();
            this.removeModifierObs();
            this.addModifierObs();
        },

        initCodeMirror: function () {
            for (var i = 0; i < this.textarea.length; i++){
                if (document.getElementById(this.textarea[i]) != null) {
                    this.codeMirror[i] = [
                        CodeMirror.fromTextArea(document.getElementById(this.textarea[i]), {
                            lineNumbers: true,
                            autoRefresh: true,
                            styleActiveLine: true
                        })
                    ];
                }
            }
        },

        /**
         * Load template
         */
        loadTemplate: function () {
            var self             = this;
            var loadTempBtn      = $('#load-template');
            var successPageStyle = $("#mpthankyoupage_custom_style");

            if (successPageStyle.val() === '1') {
                loadTempBtn.show();
            }
            successPageStyle.on('change', function () {
                loadTempBtn.toggle('show');
            });
            loadTempBtn.on('click', function () {
                self.sendAjax();
            });
        },

        sendAjax: function () {
            var params         = {
                templateId: this.styleDropdown.val()
            };
            var url            = this.options.url;
            var editorHtml     = this.codeMirror[1][0],
                editorCss      = this.codeMirror[2][0],
                hyvaEditorHtml = this.codeMirror[3] ? this.codeMirror[3][0] : null,
                hyvaEditorCss  = this.codeMirror[4] ? this.codeMirror[4][0] : null;
            $.ajax({
                method: 'POST',
                url: url,
                data: params,
                showLoader: true,
                success: function (response) {
                    if (response.status) {
                        editorHtml.setValue(response.templateHtml);
                        editorCss.setValue(response.templateCss);
                        hyvaEditorHtml?.setValue(response.hyvaTemplateHtml);
                        hyvaEditorCss?.setValue(response.hyvaTemplateCss);
                    }
                }
            })
        },

        changeImageUrl: function () {
            var ImgUrls = this.ImgUrls,
                demoImg = $('#demo-image');
            this.styleDropdown.on('change', function () {
                demoImg.show();
                demoImg.attr('src', ImgUrls[$(this).val()]);
            });
        },

        initPopup: function () {
            var options = {
                type: 'slide',
                responsive: true,
                innerScroll: true,
                title: $t('Insert Variable'),
                subTitle: $t('Click to each attribute to add filter & insert it to the template '),
                buttons: []
            };
            modal(options, $('#insert-variable-popup'));
        },

        openVariableChooser: function () {
            var self = this;
            $('.insert-variable').each(function (index) {
                var el = $(this);
                el.on('click', function () {
                    self.editor = self.codeMirror[index][0];
                    self.VariablesPopup.modal('openModal');
                });
            });
        },

        /**
         * Insert variable to editor at cursor
         */
        insertVariable: function () {
            var self = this;
            $('.insert').on('click', function () {
                self.VariablesPopup.modal('closeModal');
                var doc    = self.editor.getDoc();
                var cursor = doc.getCursor();
                doc.replaceRange($(this).siblings('.liquid-variable').text(), cursor);
            });
        },

        initDraggable: function () {
            var self = this;
            $('#insert-variable-popup .modifier-group').sortable({
                stop: function () {
                    var attr_code = $(this).attr('data-code');
                    self.updateVariable(attr_code);
                }
            });
        },
        rowSelectObs: function () {
            var self = this;
            this.VariablesPopup.on('change', 'select', function () {
                var elf       = $(this);
                var paramsEl  = elf.siblings('.params');
                var attr_code = elf.parents('.modifier').attr('data-code');
                paramsEl.html('');
                if (elf.val() !== 0) {
                    _.each(self.modifiersData[this.value].params, function (record) {
                        paramsEl.append('<span class="modifier-param">' + record.label + '</span><input class="modifier-param" type="text" data-code="' + attr_code + '"/>')
                    });
                }
                self.updateVariable(attr_code);
            });
        },
        inputObs: function () {
            var self = this;
            self.VariablesPopup.on('change', 'input', function () {
                var attr_code = $(this).attr('data-code');
                self.updateVariable(attr_code);
            });
        },
        removeModifierObs: function () {
            var self = this;
            self.VariablesPopup.on('click', '.remove-modifier', function () {
                var rowModifier = $(this).parent().parent().parent();
                var attr_code   = $(this).parents('.modifier').attr('data-code');
                $(this).parent().parent().remove();
                self.updateVariable(attr_code);

                if (!rowModifier.children().length) {
                    rowModifier.parent().removeClass('show');
                }
            });
        },
        addModifierObs: function () {
            var self = this;
            self.VariablesPopup.on('click', '.add-modifier', function () {
                var rowModifier = $(this).parent().siblings('.row-modifier');
                if (!rowModifier.hasClass('show')) {
                    rowModifier.addClass('show');
                }

                var opt       = '';
                var attr_code = $(this).parents('.attr-code').attr('data-code');
                _.each(self.modifiersData, function (record, index) {
                    opt += '<option value="' + index + '">' + record.label + '</option>';
                });
                var modifierEl = '<div class="modifier" data-code="' + attr_code + '"><div class="row"><select><option value="0">' + $t('--Please Select--') + '</option>' +
                    opt +
                    '</select><div class="params"></div><button title="Delete" type="button" class="action- scalable delete delete-option remove-modifier"><span>' + $t('Delete') + '</span></button></div></div>';
                $(this).parent().parent().find('.modifier-group').append(modifierEl);
            });
        },
        updateVariable: function (attr_code) {
            var parentEl = $('[data-code="' + attr_code + '"]');
            var str      = '{{ ';
            str += attr_code;
            parentEl.find('.modifier').each(function () {
                var modifier = $(this).find('select').val();
                if (modifier && modifier !== '0') {
                    str += ' | ' + modifier;
                }
                var params = $(this).find('input.modifier-param');
                if (params.length) {
                    str += ': ';

                    params.each(function (index) {
                        if (index === (params.length - 1)) {
                            str += "'" + this.value + "'";
                            return;
                        }
                        str += "'" + this.value + "', ";
                    });
                }
            });
            str += ' }}';
            parentEl.find('.liquid-variable').text(str);
        }
    });
    return $.mageplaza.thankyoupage;
});
