<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

/**
 * Schema and provenance for the USP A icon list component.
 */
class UspADefinition extends Definition
{
    public function __construct()
    {
        parent::__construct(
            id: 'usp_a',
            label: 'Icon Benefits',
            template: 'Secomm_UiWidget::components/usp/a.phtml',
            group: 'content',
            schemaVersion: 1,
            fields: $this->getFieldSchema(),
            sourceComponent: 'usp/A-icons',
            sourceVersion: '2.8.0',
            sortOrder: 70
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getFieldSchema(): array
    {
        return [
            ['name' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow', 'required' => true, 'max_length' => 80],
            ['name' => 'heading', 'type' => 'text', 'label' => 'Heading', 'required' => true, 'max_length' => 160],
            [
                'name' => 'items', 'type' => 'collection', 'label' => 'Benefits',
                'required' => true, 'min_items' => 2, 'max_items' => 8,
                'fields' => [
                    ['name' => 'title', 'type' => 'text', 'label' => 'Benefit Title', 'required' => true, 'max_length' => 120],
                    [
                        'name' => 'text', 'type' => 'textarea', 'label' => 'Benefit Description',
                        'required' => true, 'max_length' => 800,
                    ],
                    [
                        'name' => 'icon', 'type' => 'select', 'label' => 'Icon', 'default' => 'shield_check',
                        'options' => $this->options([
                            'shield_check' => 'Shield Check', 'truck' => 'Truck',
                            'gift' => 'Gift', 'receipt' => 'Receipt',
                        ]),
                    ],
                    [
                        'name' => 'image', 'type' => 'media-image', 'label' => 'Custom Image',
                        'max_length' => 2048,
                        'description' => 'Optional. When selected, this image replaces the chosen icon.',
                    ],
                    [
                        'name' => 'image_alt', 'type' => 'text', 'label' => 'Custom Image Alt Text',
                        'max_length' => 255, 'required_with' => ['image'],
                    ],
                    ['name' => 'url', 'type' => 'url', 'label' => 'Benefit URL', 'max_length' => 2048],
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
