<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

/**
 * Schema and provenance for the Categories A image grid component.
 */
class CategoriesADefinition extends Definition
{
    public function __construct()
    {
        parent::__construct(
            id: 'categories_a',
            label: 'Image Category Grid',
            template: 'Secomm_UiWidget::components/categories/a.phtml',
            group: 'content',
            schemaVersion: 1,
            fields: $this->getFieldSchema(),
            sourceComponent: 'categories/A-grid-images',
            sourceVersion: '2.8.0',
            sortOrder: 30
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
            [
                'name' => 'mobile_slider', 'type' => 'yesno', 'label' => 'Enable Mobile Slider',
                'default' => true,
            ],
            [
                'name' => 'items', 'type' => 'collection', 'label' => 'Categories',
                'required' => true, 'min_items' => 1, 'max_items' => 12,
                'fields' => [
                    [
                        'name' => 'label', 'type' => 'text', 'label' => 'Category Label',
                        'required' => true, 'max_length' => 120,
                    ],
                    [
                        'name' => 'image', 'type' => 'media-image', 'label' => 'Image',
                        'required' => true, 'max_length' => 2048,
                    ],
                    [
                        'name' => 'image_alt', 'type' => 'text', 'label' => 'Image Alt Text',
                        'required' => true, 'max_length' => 255,
                    ],
                    [
                        'name' => 'url', 'type' => 'url', 'label' => 'Category URL',
                        'required' => true, 'max_length' => 2048,
                    ],
                    [
                        'name' => 'loading', 'type' => 'select', 'label' => 'Image Loading', 'default' => 'lazy',
                        'options' => $this->options(['lazy' => 'Lazy', 'eager' => 'Eager']),
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
