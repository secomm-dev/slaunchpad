<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

/**
 * Schema and provenance for the Slider B logo marquee component.
 */
class SliderBDefinition extends Definition
{
    public function __construct()
    {
        parent::__construct(
            id: 'slider_b',
            label: 'Logo Marquee',
            template: 'Secomm_UiWidget::components/slider/b.phtml',
            group: 'content',
            schemaVersion: 1,
            fields: $this->getFieldSchema(),
            sourceComponent: 'slider/B-marquee',
            sourceVersion: '2.8.0',
            sortOrder: 52
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getFieldSchema(): array
    {
        return [
            ['name' => 'heading', 'type' => 'text', 'label' => 'Heading', 'required' => true, 'max_length' => 160],
            ['name' => 'show_title', 'type' => 'yesno', 'label' => 'Show Heading', 'default' => true],
            [
                'name' => 'browse_label', 'type' => 'text', 'label' => 'Browse All Label',
                'max_length' => 80, 'required_with' => ['browse_url'],
            ],
            [
                'name' => 'browse_url', 'type' => 'url', 'label' => 'Browse All URL',
                'max_length' => 2048, 'required_with' => ['browse_label'],
            ],
            ['name' => 'browse_open_in_new', 'type' => 'yesno', 'label' => 'Open Browse Link in New Tab', 'default' => false],
            [
                'name' => 'speed', 'type' => 'select', 'label' => 'Animation Speed', 'default' => 'normal',
                'options' => $this->options(['slow' => 'Slow', 'normal' => 'Normal', 'fast' => 'Fast']),
            ],
            [
                'name' => 'direction', 'type' => 'select', 'label' => 'Animation Direction', 'default' => 'left',
                'options' => $this->options(['left' => 'Left', 'right' => 'Right']),
            ],
            ['name' => 'pause_on_interaction', 'type' => 'yesno', 'label' => 'Pause on Hover or Focus', 'default' => true],
            [
                'name' => 'items', 'type' => 'collection', 'label' => 'Logos',
                'required' => true, 'min_items' => 3, 'max_items' => 12,
                'fields' => [
                    [
                        'name' => 'image', 'type' => 'media-image', 'label' => 'Logo Image',
                        'required' => true, 'max_length' => 2048,
                    ],
                    [
                        'name' => 'image_alt', 'type' => 'text', 'label' => 'Logo Alt Text',
                        'required' => true, 'max_length' => 255,
                    ],
                    ['name' => 'width', 'type' => 'integer', 'label' => 'Logo Width', 'default' => 270, 'min' => 40, 'max' => 600],
                    ['name' => 'height', 'type' => 'integer', 'label' => 'Logo Height', 'default' => 95, 'min' => 20, 'max' => 300],
                    ['name' => 'url', 'type' => 'url', 'label' => 'Logo URL', 'max_length' => 2048],
                    ['name' => 'open_in_new', 'type' => 'yesno', 'label' => 'Open in New Tab', 'default' => false],
                ],
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
