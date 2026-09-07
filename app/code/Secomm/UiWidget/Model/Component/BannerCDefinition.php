<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

/**
 * Schema and provenance for the Banner C text component.
 */
class BannerCDefinition extends Definition
{
    public function __construct()
    {
        parent::__construct(
            id: 'banner_c',
            label: 'Text Banner',
            template: 'Secomm_UiWidget::components/banner/c.phtml',
            group: 'content',
            schemaVersion: 1,
            fields: $this->getFieldSchema(),
            sourceComponent: 'banner/C-text',
            sourceVersion: '2.8.0',
            sortOrder: 18
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getFieldSchema(): array
    {
        return [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true, 'max_length' => 120],
            [
                'name' => 'content', 'type' => 'trusted-rich-text', 'label' => 'Content',
                'max_length' => 12000, 'editor_height' => 320,
                'description' => 'Trusted CMS content. Magento CMS directives are supported.',
            ],
            [
                'name' => 'cta_label', 'type' => 'text', 'label' => 'CTA Label',
                'max_length' => 80, 'required_with' => ['cta_url'],
            ],
            [
                'name' => 'cta_url', 'type' => 'url', 'label' => 'CTA URL',
                'max_length' => 2048, 'required_with' => ['cta_label'],
            ],
            ['name' => 'open_in_new', 'type' => 'yesno', 'label' => 'Open CTA in New Tab', 'default' => false],
            [
                'name' => 'content_alignment', 'type' => 'select',
                'label' => 'Content Alignment', 'default' => 'center',
                'options' => $this->options(['start' => 'Start', 'center' => 'Center', 'end' => 'End']),
            ],
            [
                'name' => 'text_alignment', 'type' => 'select', 'label' => 'Text Alignment', 'default' => 'center',
                'options' => $this->options(['left' => 'Left', 'center' => 'Center', 'right' => 'Right']),
            ],
            [
                'name' => 'content_width', 'type' => 'select', 'label' => 'Content Width', 'default' => 'medium',
                'options' => $this->options(['narrow' => 'Narrow', 'medium' => 'Medium', 'wide' => 'Wide']),
            ],
            [
                'name' => 'background_tone', 'type' => 'select', 'label' => 'Background Tone', 'default' => 'dark',
                'options' => $this->options(['dark' => 'Dark', 'light' => 'Light', 'brand' => 'Brand']),
            ],
            [
                'name' => 'text_tone', 'type' => 'select', 'label' => 'Text Tone', 'default' => 'light',
                'options' => $this->options(['light' => 'Light', 'dark' => 'Dark', 'brand' => 'Brand']),
            ],
            [
                'name' => 'link_appearance', 'type' => 'select', 'label' => 'CTA Appearance', 'default' => 'primary',
                'options' => $this->options([
                    'primary' => 'Primary Button', 'secondary' => 'Secondary Button',
                    'button' => 'Neutral Button', 'link' => 'Text Link', 'overlay' => 'Banner Overlay',
                ]),
            ],
        ];
    }

    /**
     * @param array<string, string> $values Option map.
     * @return array<int, array{value: string, label: string}>
     */
    private function options(array $values): array
    {
        $options = [];
        foreach ($values as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
