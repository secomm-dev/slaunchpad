<template>
    <fieldset class="admin__fieldset fieldset-wide">
        <checkbox
            id="is_active"
            :label="isNodeActiveLabel"
            :item="item"
            :value="item.is_active"
        />

        <div class="admin__field field field-title">
            <label
                class="label admin__field-label"
                for="node_type"
            >
                {{ config.translation.nodeType }}
            </label>

            <div class="admin__field-control control">
                <v-select
                    input-id="node_type"
                    :value="item.type"
                    :options="options"
                    :placeholder="config.translation.selectNodeType"
                    :get-option-label="getOptionLabel"
                    :clearable="false"
                    @input="changeType"
                />
            </div>
        </div>

        <h2>
            {{ additionalLabel }}
        </h2>

        <component
            :is="item['type']"
            :item="item"
            :config="config"
        />

        <simple-field
            id="node_name"
            v-model="item.title"
            :label="config.translation.nodeName"
            type="text"
        />

        <simple-field
            id="node_classes"
            v-model="item.classes"
            :label="config.translation.nodeClasses"
            type="text"
        />

        <div class="admin__field field field-title">
            <label
                class="label admin__field-label"
                for="customer_groups"
            >
                {{ config.translation.customerGroups }}
            </label>

            <div class="admin__field-control control">
                <v-select
                    input-id="customer_groups"
                    v-model="item.customer_groups"
                    :reduce="customer_group => customer_group.value"
                    :options="config.customerGroups"
                    aria-describedby="customer-groups-description"
                    clearable
                    multiple
                />
                <small
                    id="customer-groups-description"
                    class="admin__field-control__description"
                >
                    {{ config.translation.customerGroupsDescription }}
                </small>
            </div>
        </div>

        <template v-if="showImage">
            <image-upload
                id="image"
                :item="item"
            />

            <simple-field
                id="image_alt_text"
                v-model="item.image_alt_text"
                :label="config.translation.imageAltText"
                type="text"
            />

            <simple-field
                id="image_width"
                v-model="item.image_width"
                :label="config.translation.imageWidth"
                type="number"
            />

            <simple-field
                id="image_height"
                v-model="item.image_height"
                :label="config.translation.imageHeight"
                type="number"
            />
        </template>

        <div class="admin__field field admin__scope-old" id="launchpad_banner_content_field">
            <label class="label admin__field-label" :for="'banner_content_' + item.id">
                <span>{{ bannerContentLabel }}</span>
            </label>
            <div class="admin__field-control control">
                <textarea
                    ref="bannerContent"
                    :id="'banner_content_' + item.id"
                    class="input-text admin__control-textarea launchpad-banner-content"
                    rows="6"
                    :value="item.banner_content"
                    @input="item.banner_content = $event.target.value"
                    @focus="initBannerWysiwyg"
                ></textarea>
                <small class="admin__field-control__description">
                    {{ bannerContentDescription }}
                </small>
            </div>
        </div>

        <div class="admin__field field field-title admin__scope-old">
            <label
                class="label admin__field-label"
                :for="'launchpad_banner_mobile_' + item.id"
            >
                <span>{{ showBannerContentMobileLabel }}</span>
            </label>
            <div class="admin__field-control control">
                <input
                    :id="'launchpad_banner_mobile_' + item.id"
                    v-model="item.show_banner_content_mobile"
                    class="checkbox"
                    type="checkbox"
                    :true-value="1"
                    :false-value="0"
                >
            </div>
        </div>

        <checkbox
            v-if="showHideIfEmpty"
            id="hide_if_empty"
            :label="config.translation.hideIfEmpty"
            :value="item.hide_if_empty"
            :item="item"
            :description="config.translation.hideIfEmptyDescription"
        />

        <h2>
            {{ templatesLabel }}
        </h2>

        <template v-if="isTemplateSectionVisible">
            <template-list
                :item="item"
                :type-id="templateList['node']"
                item-key="node_template"
                template-type="node"
                :config="config"
            />
            <template-list
                :item="item"
                :type-id="templateList['submenu']"
                template-type="submenu"
                :config="config"
                item-key="submenu_template"
            />
        </template>
        <template v-else>
            <p>{{ noTemplatesMessage }}</p>
        </template>
    </fieldset>
</template>

<script>
    define(['Vue', 'mage/translate'], function(Vue, $t) {
        Vue.component('menu-type', {
            name: 'menu-type',
            props: {
                item: {
                    type: Object,
                    required: true
                },
                config: {
                    type: Object,
                    required: true
                }
            },
            data() {
                return {
                    draft: {},
                    bannerWysiwyg: null,
                    bannerWysiwygLoading: false,
                    bannerContentLabel: $t('Banner content'),
                    bannerContentDescription: $t('Leave empty to use the node-specific launchpad-menu-feature CMS block, if available.'),
                    showBannerContentMobileLabel: $t('Show banner content on mobile'),
                    isNodeActiveLabel: $t('Enabled'),
                    additionalLabel: $t('Additional type options'),
                    noTemplatesMessage: $t('There is no custom defined templates defined in theme for this node type'),
                    templatesLabel: $t('Templates'),
                    templateList: {
                      'node': 'snowMenuNodeCustomTemplates',
                      'submenu': 'snowMenuSubmenuCustomTemplates',
                    }
                }
            },
            created() {
                // Vue 2 cannot observe properties added through direct
                // assignment after the node object has been made reactive.
                // Ensure legacy and newly-created nodes carry both fields so
                // the parent JSON watcher updates `serialized_nodes` on edit.
                if (!Object.prototype.hasOwnProperty.call(this.item, 'banner_content')) {
                    this.$set(this.item, 'banner_content', null);
                }
                if (!Object.prototype.hasOwnProperty.call(this.item, 'show_banner_content_mobile')) {
                    this.$set(this.item, 'show_banner_content_mobile', 0);
                }
            },
            mounted() {
                // Render the toolbar as soon as the node edit form opens.
                // The textarea remains available if TinyMCE cannot load.
                this.$nextTick(() => {
                    this.initBannerWysiwyg(this.$refs.bannerContent);
                });
            },
            computed: {
                isTemplateSectionVisible() {
                    var nodeId = this.templateList['node'],
                        submenuId = this.templateList['submenu'],
                        typeData = this.config.fieldData[this.item['type']];

                    if (typeData[nodeId] || typeData[submenuId]) {
                        return typeData[nodeId].options.length > 0 || typeData[submenuId].options.length > 0;
                    }

                    return false;
                },
                options() {
                    var list = [];
                    for (type in this.config.nodeTypes) {
                        list.push({
                            label: this.config.nodeTypes[type],
                            value: type
                        })
                    }
                    return list;
                },
                templateOptions() {
                    return this.templateOptionsData[this.item['type']] || [];
                },
                showImage() {
                    return ['category', 'product', 'custom_url'].includes(this.item.type);
                },
                showHideIfEmpty: function() {
                    return this.item.type === 'category';
                },
            },
            watch: {
                item: {
                    handler() {
                        this.syncBannerWysiwyg();
                    },
                    deep: false
                }
            },
            beforeDestroy() {
                this._destroyed = true;
                // sync the final content back to the node payload, then
                // destroy the editor so it never points at a replaced textarea
                this.teardownBannerWysiwyg(true);
            },
            methods: {
                syncBannerWysiwyg() {
                    if (!this.bannerWysiwyg) return;
                    try {
                        this.bannerWysiwyg.setContent(this.item.banner_content || '');
                    } catch (e) {
                        // editor removed underneath (panel closed) — drop it
                        this.bannerWysiwyg = null;
                    }
                },
                teardownBannerWysiwyg(syncContent) {
                    var editor = this.bannerWysiwyg;
                    if (!editor) return;
                    this.bannerWysiwyg = null;
                    try {
                        if (syncContent) {
                            editor.push();
                        }
                        editor.remove();
                    } catch (e) {
                        // instance already gone — nothing to clean up
                    }
                },
                initBannerWysiwyg(target) {
                    // Enhance the textarea when the node edit component mounts.
                    // A focus event can retry initialization after a load error;
                    // the textarea stays usable throughout as the fallback.
                    if (this.bannerWysiwyg || this.bannerWysiwygLoading || typeof window.require !== 'function') return;
                    var self = this;
                    var textarea = target && target.target ? target.target : target;
                    if (!textarea) return;
                    this.bannerWysiwygLoading = true;
                    window.require(['tinymce'], function (tinymce) {
                        self.bannerWysiwygLoading = false;
                        if (self._destroyed || !tinymce || self.bannerWysiwyg) return;
                        tinymce.init({
                            target: textarea,
                            menubar: false,
                            toolbar: 'bold italic link bullist',
                            plugins: 'link lists',
                            skin: 'oxide',
                            setup: function (editor) {
                                // editor -> node payload (serialized_nodes flow)
                                editor.on('input change keyup SetContent', function () {
                                    self.item.banner_content = editor.getContent();
                                });
                            },
                            init_instance_callback: function (editor) {
                                if (self._destroyed) {
                                    try { editor.remove(); } catch (e) { /* gone */ }
                                    return;
                                }
                                self.bannerWysiwyg = editor;
                                editor.setContent(self.item.banner_content || '');
                            }
                        });
                    }, function () {
                        // tinymce unavailable: the plain textarea keeps working
                        self.bannerWysiwygLoading = false;
                    });
                },
                changeType(selected) {
                    if (selected && typeof selected === 'object') {
                        var type  = this.item.type,
                            value = selected.value;
                        if (type) {
                            this.draft[type] = {
                                content: this.item['content']
                            };
                        }
                        if (this.draft[value]) {
                            this.item['content'] = this.draft[value].content;
                        } else {
                            this.item['content'] = null;
                        }
                        this.item['type'] = value;
                    }
                },
                getOptionLabel(option) {
                    if (typeof option === 'object') {
                        return option.label;
                    }
                    if (option) {
                        return this.config.nodeTypes[option];
                    }
                    return option;
                }
            },
            template: template
        });
    });
</script>
