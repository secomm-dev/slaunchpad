<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

/**
 * Schema and provenance for the Slider A component.
 */
class SliderADefinition extends Definition
{
    public function __construct()
    {
        parent::__construct(
            id: 'slider_a',
            label: 'Content Slider',
            template: 'Secomm_UiWidget::components/slider/a.phtml',
            group: 'content',
            schemaVersion: 1,
            fields: $this->getFieldSchema(),
            sourceComponent: 'slider/A-basic',
            sourceVersion: '2.8.0',
            sortOrder: 50
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getFieldSchema(): array
    {
        return [
            [
                'name' => 'label', 'type' => 'text', 'label' => 'Accessible Slider Label',
                'required' => true, 'max_length' => 160,
            ],
            [
                'name' => 'show_arrows', 'type' => 'select', 'label' => 'Arrow Position', 'default' => 'end',
                'options' => $this->options([
                    'none' => 'Hidden', 'start' => 'Start', 'end' => 'End', 'both' => 'Both Sides',
                ]),
            ],
            ['name' => 'show_dots', 'type' => 'yesno', 'label' => 'Show Navigation Dots', 'default' => true],
            ['name' => 'load_first_eager', 'type' => 'yesno', 'label' => 'Load First Image Eagerly', 'default' => false],
            [
                'name' => 'slides', 'type' => 'collection', 'label' => 'Slides',
                'required' => true, 'min_items' => 2, 'max_items' => 12,
                'fields' => [
                    [
                        'name' => 'image', 'type' => 'media-image', 'label' => 'Image',
                        'required' => true, 'max_length' => 2048,
                    ],
                    [
                        'name' => 'image_alt', 'type' => 'text', 'label' => 'Image Alt Text',
                        'required' => true, 'max_length' => 255,
                    ],
                    ['name' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow', 'max_length' => 120],
                    ['name' => 'heading', 'type' => 'text', 'label' => 'Heading', 'max_length' => 180],
                    [
                        'name' => 'cta_label', 'type' => 'text', 'label' => 'CTA Label',
                        'max_length' => 80, 'required_with' => ['cta_url'],
                    ],
                    [
                        'name' => 'cta_url', 'type' => 'url', 'label' => 'CTA URL',
                        'max_length' => 2048, 'required_with' => ['cta_label'],
                    ],
                    ['name' => 'open_in_new', 'type' => 'yesno', 'label' => 'Open CTA in New Tab', 'default' => false],
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
