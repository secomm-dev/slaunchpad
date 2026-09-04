<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

/**
 * Schema and provenance for the Testimonial B card component.
 */
class TestimonialBDefinition extends Definition
{
    public function __construct()
    {
        parent::__construct(
            id: 'testimonial_b',
            label: 'Testimonial Card',
            template: 'Secomm_UiWidget::components/testimonial/b.phtml',
            group: 'content',
            schemaVersion: 1,
            fields: $this->getFieldSchema(),
            sourceComponent: 'testimonial/B-card',
            sourceVersion: '2.8.0',
            sortOrder: 61
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getFieldSchema(): array
    {
        return [
            [
                'name' => 'quote', 'type' => 'textarea', 'label' => 'Quote',
                'required' => true, 'max_length' => 2000,
            ],
            ['name' => 'author', 'type' => 'text', 'label' => 'Author Name', 'required' => true, 'max_length' => 120],
            ['name' => 'role', 'type' => 'text', 'label' => 'Author Role or Company', 'max_length' => 160],
            ['name' => 'avatar', 'type' => 'media-image', 'label' => 'Author Avatar', 'max_length' => 2048],
            [
                'name' => 'avatar_alt', 'type' => 'text', 'label' => 'Avatar Alt Text',
                'max_length' => 255, 'required_with' => ['avatar'],
            ],
            [
                'name' => 'rating', 'type' => 'select', 'label' => 'Rating', 'default' => '0',
                'options' => $this->options([
                    '0' => 'No Rating', '1' => '1 Star', '2' => '2 Stars',
                    '3' => '3 Stars', '4' => '4 Stars', '5' => '5 Stars',
                ]),
            ],
            [
                'name' => 'loading', 'type' => 'select', 'label' => 'Image Loading', 'default' => 'lazy',
                'options' => $this->options(['lazy' => 'Lazy', 'eager' => 'Eager']),
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
            $options[] = ['value' => (string)$value, 'label' => $label];
        }

        return $options;
    }
}
