<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

/**
 * Production schema and provenance for the Banner A vertical slice.
 */
class BannerADefinition extends Definition
{
    /**
     * Initialize the stable Banner A definition.
     */
    public function __construct()
    {
        parent::__construct(
            id: 'banner_a',
            label: 'Hero Banner',
            template: 'Secomm_UiWidget::components/banner/a.phtml',
            group: 'content',
            schemaVersion: 1,
            fields: $this->getFieldSchema(),
            sourceComponent: 'banner/A-default',
            sourceVersion: '2.8.0',
            sortOrder: 10
        );
    }

    /**
     * Return the ordered Admin field schema.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getFieldSchema(): array
    {
        return [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true, 'max_length' => 120],
            ['name' => 'subtitle', 'type' => 'textarea', 'label' => 'Subtitle', 'max_length' => 500],
            [
                'name' => 'mobile_image', 'type' => 'media-image', 'label' => 'Mobile Image', 'required' => true,
                'description' => 'Used on mobile and as the desktop fallback.',
            ],
            ['name' => 'desktop_image', 'type' => 'media-image', 'label' => 'Desktop Image'],
            [
                'name' => 'image_alt', 'type' => 'text', 'label' => 'Image Alt Text',
                'required' => true, 'max_length' => 160,
            ],
            [
                'name' => 'loading', 'type' => 'select', 'label' => 'Image Loading', 'default' => 'lazy',
                'options' => $this->options(['lazy' => 'Lazy', 'eager' => 'Eager']),
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
                'label' => 'Content Alignment', 'default' => 'end',
                'options' => $this->options(['start' => 'Start', 'center' => 'Center', 'end' => 'End']),
            ],
            [
                'name' => 'text_alignment', 'type' => 'select', 'label' => 'Text Alignment', 'default' => 'center',
                'options' => $this->options(['left' => 'Left', 'center' => 'Center', 'right' => 'Right']),
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
            ['name' => 'use_card', 'type' => 'yesno', 'label' => 'Use Content Card', 'default' => false],
            ['name' => 'use_gradient', 'type' => 'yesno', 'label' => 'Use Gradient Overlay', 'default' => false],
            [
                'name' => 'gradient_angle', 'type' => 'integer', 'label' => 'Gradient Angle',
                'default' => 5, 'min' => 0, 'max' => 360,
            ],
        ];
    }

    /**
     * Convert a value/label map into schema options.
     *
     * @param array $values Option map.
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
