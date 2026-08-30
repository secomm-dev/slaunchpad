<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

/**
 * Schema and provenance for the USP C compact benefits component.
 */
class UspCDefinition extends Definition
{
    public function __construct()
    {
        parent::__construct(
            id: 'usp_c',
            label: 'Compact Benefits',
            template: 'Secomm_UiWidget::components/usp/c.phtml',
            group: 'content',
            schemaVersion: 1,
            fields: $this->getFieldSchema(),
            sourceComponent: 'usp/C-compact',
            sourceVersion: '2.8.0',
            sortOrder: 72
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getFieldSchema(): array
    {
        return [[
            'name' => 'items', 'type' => 'collection', 'label' => 'Compact Benefits',
            'required' => true, 'min_items' => 2, 'max_items' => 6,
            'fields' => [
                ['name' => 'label', 'type' => 'text', 'label' => 'Benefit Label', 'required' => true, 'max_length' => 120],
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
