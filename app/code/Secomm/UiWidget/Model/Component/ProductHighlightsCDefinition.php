<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

/**
 * Schema and provenance for the content-only Product Highlights C component.
 */
class ProductHighlightsCDefinition extends Definition
{
    public function __construct()
    {
        parent::__construct(
            id: 'product_highlights_c',
            label: 'Product Highlights',
            template: 'Secomm_UiWidget::components/product-highlights/c.phtml',
            group: 'content',
            schemaVersion: 1,
            fields: $this->getFieldSchema(),
            sourceComponent: 'product-data/C-highlights',
            sourceVersion: '2.8.0',
            sortOrder: 46
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getFieldSchema(): array
    {
        return [[
            'name' => 'highlights', 'type' => 'collection', 'label' => 'Highlights',
            'required' => true, 'min_items' => 1, 'max_items' => 12,
            'fields' => [
                [
                    'name' => 'title', 'type' => 'text', 'label' => 'Highlight Title',
                    'required' => true, 'max_length' => 160,
                ],
                [
                    'name' => 'content', 'type' => 'trusted-rich-text', 'label' => 'Highlight Content',
                    'required' => true, 'max_length' => 8000, 'editor_height' => 440,
                    'description' => 'Trusted CMS content. Magento CMS directives are supported.',
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
                    'name' => 'loading', 'type' => 'select', 'label' => 'Image Loading', 'default' => 'lazy',
                    'options' => $this->options(['lazy' => 'Lazy', 'eager' => 'Eager']),
                ],
                [
                    'name' => 'image_position', 'type' => 'select', 'label' => 'Image Position', 'default' => 'left',
                    'options' => $this->options(['left' => 'Left', 'right' => 'Right']),
                ],
            ],
        ]];
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
