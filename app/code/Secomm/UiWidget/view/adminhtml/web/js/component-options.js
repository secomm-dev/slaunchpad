/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'mage/translate',
    'mage/adminhtml/wysiwyg/tiny_mce/setup',
    'mage/adminhtml/wysiwyg/events',
    'mage/validation'
], function ($, $t, WysiwygSetup, wysiwygEvents) {
    'use strict';

    $.validator.addMethod(
        'validate-secomm-ui-component',
        function (value, element) {
            var validateComponent = $(element).data('validate-secomm-ui-component');

            return typeof validateComponent !== 'function' || validateComponent();
        },
        $t('Complete all required component fields before inserting the widget.')
    );

    function canonicalize(value) {
        if (Array.isArray(value)) {
            return value.map(canonicalize);
        }
        if (value && typeof value === 'object') {
            return Object.keys(value).sort().reduce(function (result, key) {
                result[key] = canonicalize(value[key]);
                return result;
            }, {});
        }
        return value;
    }

    function encode(data) {
        var bytes = new TextEncoder().encode(JSON.stringify({
            data: canonicalize(data),
            version: 1
        }));
        var binary = Array.prototype.map.call(bytes, function (byte) {
            return String.fromCharCode(byte);
        }).join('');

        return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function decode(payload) {
        if (!payload || !/^[A-Za-z0-9_-]+$/.test(payload)) {
            return {};
        }
        try {
            var base64 = payload.replace(/-/g, '+').replace(/_/g, '/');
            var bytes = window.atob(base64 + '='.repeat((4 - base64.length % 4) % 4));
            var json = new TextDecoder().decode(Uint8Array.from(bytes, function (char) {
                return char.charCodeAt(0);
            }));
            var envelope = JSON.parse(json);

            return envelope.version === 1 && envelope.data && typeof envelope.data === 'object'
                ? envelope.data : {};
        } catch (error) {
            return {};
        }
    }

    function createInput(field, value, id) {
        var type = field.type || 'text';
        var input;

        if (type === 'textarea' || type === 'trusted-rich-text') {
            input = $('<textarea/>', {id: id, rows: 4}).val(value || '');
        } else if (type === 'select' || type === 'yesno') {
            input = $('<select/>', {id: id});
            var options = type === 'yesno' ? [
                {value: '1', label: $t('Yes')}, {value: '0', label: $t('No')}
            ] : (field.options || []);
            options.forEach(function (option) {
                input.append($('<option/>', {value: option.value, text: $t(option.label)}));
            });
            input.val(value === true ? '1' : value === false ? '0' : value);
        } else {
            input = $('<input/>', {
                id: id,
                type: type === 'media-image' ? 'hidden'
                    : type === 'integer' || type === 'decimal' ? 'number' : 'text'
            }).val(value === undefined || value === null ? '' : value);
            if (field.min !== undefined) {
                input.attr('min', field.min);
            }
            if (field.max !== undefined) {
                input.attr('max', field.max);
            }
            if (type === 'decimal') {
                input.attr('step', 'any');
            }
            if (type === 'media-image') {
                // Magento's media chooser otherwise returns a temporary Admin directive URL.
                input.data('force_static_path', true);
                input.attr('data-secomm-ui-media-target', 'true');
            }
        }
        if (type !== 'media-image') {
            input.addClass('admin__control-' + (input.is('select') ? 'select' : input.is('textarea') ? 'textarea' : 'text'));
        }
        if (field.required && type !== 'media-image') {
            input.attr('required', true).addClass('required-entry');
        }

        return input;
    }

    function normalizeInput(field, value) {
        if (field.type === 'yesno') {
            return value === '1';
        }
        if (field.type === 'integer') {
            return value === '' ? null : parseInt(value, 10);
        }
        if (field.type === 'decimal') {
            return value === '' ? null : parseFloat(value);
        }
        return value;
    }

    function getMediaBrowserUrl(config, targetId) {
        var wysiwygConfig = config.wysiwygConfig || {};
        var baseUrl = wysiwygConfig.files_browser_window_url || config.mediaBrowserUrl;
        var storeId = wysiwygConfig.store_id || 0;

        return baseUrl + 'target_element_id/' + encodeURIComponent(targetId)
            + '/store/' + encodeURIComponent(storeId) + '/type/image/';
    }

    function addMediaImageControl(input, control, config) {
        var preview = $('<div/>', {class: 'secomm-ui-media-preview'});
        var image = $('<img/>', {
            alt: $t('Image preview'),
            class: 'secomm-ui-media-preview__image'
        });
        var status = $('<span/>', {
            class: 'secomm-ui-media-preview__status',
            text: $t('No image selected.')
        });
        var actions = $('<div/>', {class: 'secomm-ui-media-actions'});

        function updatePreview() {
            var source = String(input.val() || '').trim();

            if (!source) {
                image.attr('hidden', true).removeAttr('src');
                status.text($t('No image selected.')).removeAttr('hidden');
                preview.addClass('is-empty');
                return;
            }
            status.attr('hidden', true);
            image.removeAttr('hidden').attr('src', source);
            preview.removeClass('is-empty');
        }

        image.on('error', function () {
            image.attr('hidden', true);
            status.text($t('Preview unavailable.')).removeAttr('hidden');
            preview.addClass('has-error');
        }).on('load', function () {
            status.attr('hidden', true);
            preview.removeClass('has-error');
        });
        input.on('change input', updatePreview);
        preview.append(image, status);
        control.append(preview);
        $('<button/>', {type: 'button', class: 'action-default', text: $t('Select from Gallery')})
            .on('click', function () {
                window.MediabrowserUtility.openDialog(
                    getMediaBrowserUrl(config, input.attr('id')),
                    false,
                    false,
                    $t('Select Images'),
                    {targetElementId: input.attr('id')}
                );
            }).appendTo(actions);
        $('<button/>', {type: 'button', class: 'action-delete', text: $t('Remove Image')})
            .on('click', function () {
                input.val('').trigger('input').trigger('change');
            }).appendTo(actions);
        control.append(actions);
        updatePreview();
    }

    return function (config, element) {
        var root = $(element);

        if (root.data('secomm-ui-initialized')) {
            return;
        }
        root.data('secomm-ui-initialized', true);
        root.closest('.admin__field-control')
            .addClass('secomm-ui-options-control')
            .closest('.admin__field')
            .addClass('secomm-ui-options-field');
        var fieldset = root.closest('fieldset');
        var payload = fieldset.find('[name="parameters[payload]"]').first();
        var component = fieldset.find('[name="parameters[component]"]').first();
        var schemaVersion = fieldset.find('[name="parameters[schema_version]"]').first();
        var fieldsRoot = root.find('[data-role="fields"]');
        var payloadError = root.find('[data-role="payload-error"]');
        var state = decode(payload.val());
        var sequence = 0;
        var editors = {};
        var idPrefix = String(root.data('payload-id')).replace(/[^A-Za-z0-9_-]/g, '-');

        function removeEditors(scope) {
            Object.keys(editors).forEach(function (id) {
                var textarea = document.getElementById(id);

                if (scope && textarea && !$.contains(scope[0], textarea)) {
                    return;
                }
                var instance = editors[id].wysiwygInstance;
                var editor = instance.get(id);

                if (editor) {
                    editor.remove();
                }
                instance.removeEvents(id);
                delete editors[id];
            });
        }

        function initializeTrustedEditor(input, field, onChange) {
            var id = input.attr('id');
            var editorConfig = $.extend(true, {}, config.wysiwygConfig);

            if (!$.contains(document, input[0])) {
                return;
            }
            if (field.editor_height) {
                editorConfig.height = String(field.editor_height) + 'px';
            }
            var setup = new WysiwygSetup(id, editorConfig);

            setup.eventBus.attachEventHandler(wysiwygEvents.afterChangeContent, function () {
                var editor = setup.wysiwygInstance.get(id);

                onChange(editor ? editor.getContent() : input.val());
            });
            setup.setup('exact');
            editors[id] = setup;
        }

        function persist() {
            var encoded = encode(state);

            if (encoded.length <= config.limits.maxEncodedBytes) {
                payloadError.attr('hidden', true).text('');
                payload.val(encoded).trigger('change');
                return true;
            }
            payload.val('').trigger('change');
            payloadError.removeAttr('hidden').text($t(
                'The component content is too large. Reduce the rich text or number of items before saving.'
            ));

            return false;
        }

        function isMissing(value) {
            return value === undefined || value === null || value === '';
        }

        function isRequired(field, rowData) {
            if (field.required) {
                return true;
            }

            return (field.required_with || []).some(function (peer) {
                return !isMissing(rowData[peer]);
            });
        }

        function addFieldError(path, message) {
            var row = fieldsRoot.find('[data-path="' + path + '"]').first();
            var control = row.children('.admin__field-control').first();

            row.addClass('_error');
            control.append($('<div/>', {
                class: 'mage-error secomm-ui-field-error',
                text: message
            }));
        }

        function validateFields(fields, rowData, pathPrefix) {
            var valid = true;

            (fields || []).forEach(function (field) {
                var path = pathPrefix ? pathPrefix + '.' + field.name : field.name;
                var value = rowData[field.name];

                if (field.type === 'collection') {
                    var rows = Array.isArray(value) ? value : [];
                    var minimum = Math.max(field.min_items || 0, field.required ? 1 : 0);

                    if (rows.length < minimum) {
                        addFieldError(path, $t('Add at least %1 item(s).').replace('%1', minimum));
                        valid = false;
                    }
                    rows.forEach(function (item, index) {
                        if (!validateFields(field.fields, item, path + '.' + index)) {
                            valid = false;
                        }
                    });
                    return;
                }
                if (isRequired(field, rowData) && isMissing(value)) {
                    addFieldError(path, $t('This is a required field.'));
                    valid = false;
                }
            });

            return valid;
        }

        function validateComponent() {
            var schema = config.schemas[component.val()];
            var valid;

            Object.keys(editors).forEach(function (id) {
                var editor = editors[id].wysiwygInstance.get(id);

                if (editor) {
                    $('#' + id).val(editor.getContent()).trigger('change');
                }
            });
            fieldsRoot.find('.secomm-ui-field-error').remove();
            fieldsRoot.find('._error').removeClass('_error');
            valid = !!schema && validateFields(schema.fields, state, '');
            if (!valid) {
                payloadError.removeAttr('hidden').text($t(
                    'Complete all required component fields before inserting the widget.'
                ));
            } else {
                payloadError.attr('hidden', true).text('');
            }

            return valid;
        }

        function fieldRow(field, value, path, onChange) {
            var id = 'secomm-ui-' + idPrefix + '-' + (++sequence);
            var row = $('<div/>', {class: 'admin__field field'});
            var label = $('<label/>', {class: 'label admin__field-label', for: id})
                .append($('<span/>', {text: $t(field.label || field.name)}));
            var control = $('<div/>', {class: 'admin__field-control control'});
            var input = createInput(field, value, id);

            if (field.required) {
                row.addClass('_required');
                input.attr('aria-required', 'true');
            }

            input.on('change input', function () {
                onChange(normalizeInput(field, $(this).val()));
            });
            control.append(input);
            if (field.type === 'media-image') {
                addMediaImageControl(input, control, config);
            } else if (field.type === 'media') {
                $('<button/>', {type: 'button', class: 'action-default', text: $t('Select from Gallery')})
                    .on('click', function () {
                        window.MediabrowserUtility.openDialog(
                            getMediaBrowserUrl(config, id),
                            false,
                            false,
                            $t('Select Images'),
                            {targetElementId: id}
                        );
                    }).appendTo(control);
            }
            if (field.description) {
                control.append($('<div/>', {class: 'note', text: $t(field.description)}));
            }
            row.attr('data-path', path).append(label, control);
            if (field.type === 'trusted-rich-text') {
                row.addClass('secomm-ui-rich-text-field');
                window.setTimeout(function () {
                    initializeTrustedEditor(input, field, onChange);
                }, 0);
            }
            return row;
        }

        function renderCollection(field, rows, path) {
            var wrapper = $('<div/>', {class: 'admin__field field secomm-ui-repeater'});
            var control = $('<div/>', {class: 'admin__field-control control'});
            var list = $('<div/>', {'data-role': 'items'});
            var maxItems = Math.min(field.max_items || config.limits.maxItems, config.limits.maxItems);

            function renderRows() {
                removeEditors(list);
                list.empty();
                rows.forEach(function (rowData, index) {
                    var item = $('<fieldset/>', {class: 'fieldset admin__fieldset'});
                    (field.fields || []).forEach(function (nested) {
                        item.append(fieldRow(nested, rowData[nested.name], path + '.' + index + '.' + nested.name,
                            function (value) {
                                rowData[nested.name] = value;
                                persist();
                            }));
                    });
                    $('<button/>', {type: 'button', class: 'action-delete', text: $t('Remove')}).on('click', function () {
                        rows.splice(index, 1); renderRows(); persist();
                    }).appendTo(item);
                    $('<button/>', {type: 'button', class: 'action-default', text: $t('Move Up'), disabled: index === 0})
                        .on('click', function () {
                            rows.splice(index - 1, 0, rows.splice(index, 1)[0]); renderRows(); persist();
                        }).appendTo(item);
                    $('<button/>', {
                        type: 'button', class: 'action-default', text: $t('Move Down'), disabled: index === rows.length - 1
                    }).on('click', function () {
                        rows.splice(index + 1, 0, rows.splice(index, 1)[0]); renderRows(); persist();
                    }).appendTo(item);
                    list.append(item);
                });
            }
            wrapper.attr('data-path', path);
            if (field.required) {
                wrapper.addClass('_required');
            }
            $('<label/>', {class: 'label admin__field-label'})
                .append($('<span/>', {text: $t(field.label || field.name)}))
                .appendTo(wrapper);
            $('<button/>', {type: 'button', class: 'action-add', text: $t('Add Item')}).on('click', function () {
                if (rows.length < maxItems) {
                    rows.push({}); renderRows(); persist();
                }
            }).appendTo(control);
            control.append(list); wrapper.append(control); renderRows();
            return wrapper;
        }

        function render() {
            removeEditors();
            fieldsRoot.empty();
            var schema = config.schemas[component.val()];
            if (!schema) {
                state = {}; payload.val(''); schemaVersion.val(''); return;
            }
            schemaVersion.val(schema.schemaVersion);
            (schema.fields || []).forEach(function (field) {
                if (field.type === 'collection') {
                    state[field.name] = Array.isArray(state[field.name]) ? state[field.name] : [];
                    fieldsRoot.append(renderCollection(field, state[field.name], field.name));
                    return;
                }
                fieldsRoot.append(fieldRow(field, state[field.name], field.name, function (value) {
                    state[field.name] = value; persist();
                }));
            });
            persist();
        }

        component.on('change', function () {
            state = {};
            render();
        });
        payload.data('validate-secomm-ui-component', validateComponent);
        render();
    };
});
