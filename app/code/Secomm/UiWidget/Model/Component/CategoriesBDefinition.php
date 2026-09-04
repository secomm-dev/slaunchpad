<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

/**
 * Schema and provenance for the Categories B pattern grid component.
 */
class CategoriesBDefinition extends Definition
{
    public function __construct()
    {
        parent::__construct(
            id: 'categories_b',
            label: 'Pattern Category Grid',
            template: 'Secomm_UiWidget::components/categories/b.phtml',
            group: 'content',
            schemaVersion: 1,
            fields: $this->getFieldSchema(),
            sourceComponent: 'categories/B-grid-patterns',
            sourceVersion: '2.8.0',
            sortOrder: 32
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getFieldSchema(): array
    {
        return [
            ['name' => 'heading', 'type' => 'text', 'label' => 'Heading', 'required' => true, 'max_length' => 120],
            [
                'name' => 'browse_label', 'type' => 'text', 'label' => 'Browse All Label',
                'max_length' => 80, 'required_with' => ['browse_url'],
            ],
            [
                'name' => 'browse_url', 'type' => 'url', 'label' => 'Browse All URL',
                'max_length' => 2048, 'required_with' => ['browse_label'],
            ],
            ['name' => 'browse_open_in_new', 'type' => 'yesno', 'label' => 'Open Browse Link in New Tab', 'default' => false],
            ['name' => 'mobile_slider', 'type' => 'yesno', 'label' => 'Enable Mobile Slider', 'default' => true],
            [
                'name' => 'items', 'type' => 'collection', 'label' => 'Categories',
                'required' => true, 'min_items' => 1, 'max_items' => 12,
                'fields' => [
                    [
                        'name' => 'label', 'type' => 'text', 'label' => 'Category Label',
                        'required' => true, 'max_length' => 120,
                    ],
                    [
                        'name' => 'url', 'type' => 'url', 'label' => 'Category URL',
                        'required' => true, 'max_length' => 2048,
                    ],
                    [
                        'name' => 'pattern', 'type' => 'select', 'label' => 'Pattern', 'default' => 'bank_note',
                        'options' => $this->options(['wiggle' => 'Wiggle', 'bank_note' => 'Bank Note']),
                    ],
                    [
                        'name' => 'background', 'type' => 'select', 'label' => 'Background Color', 'default' => 'blue',
                        'options' => $this->options(['pink' => 'Pink', 'blue' => 'Blue']),
                    ],
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
