<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

/**
 * Schema and provenance for the Generic Content A component.
 */
class GenericContentADefinition extends Definition
{
    public function __construct()
    {
        parent::__construct(
            id: 'generic_content_a',
            label: 'Generic Content A',
            template: 'Secomm_UiWidget::components/generic-content/a.phtml',
            group: 'content',
            schemaVersion: 1,
            fields: [
                ['name' => 'heading', 'type' => 'text', 'label' => 'Heading', 'max_length' => 160],
                [
                    'name' => 'content', 'type' => 'trusted-rich-text', 'label' => 'Content',
                    'required' => true, 'max_length' => 12000,
                    'description' => 'Trusted CMS content. Magento CMS directives are supported.',
                ],
                [
                    'name' => 'alignment', 'type' => 'select', 'label' => 'Text Alignment',
                    'default' => 'left', 'options' => $this->options(
                        ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']
                    ),
                ],
                [
                    'name' => 'width', 'type' => 'select', 'label' => 'Content Width',
                    'default' => 'medium', 'options' => $this->options(
                        ['narrow' => 'Narrow', 'medium' => 'Medium', 'wide' => 'Wide']
                    ),
                ],
            ],
            sourceComponent: 'generic-content/A-text',
            sourceVersion: '2.8.0',
            sortOrder: 40
        );
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
