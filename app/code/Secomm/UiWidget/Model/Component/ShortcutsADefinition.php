<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

/**
 * Schema and provenance for the Shortcuts A component.
 */
class ShortcutsADefinition extends Definition
{
    public function __construct()
    {
        parent::__construct(
            id: 'shortcuts_a',
            label: 'Shortcut Links',
            template: 'Secomm_UiWidget::components/shortcuts/a.phtml',
            group: 'content',
            schemaVersion: 1,
            fields: $this->getFieldSchema(),
            sourceComponent: 'shortcuts/A-simple',
            sourceVersion: '2.8.0',
            sortOrder: 48
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getFieldSchema(): array
    {
        return [[
            'name' => 'items', 'type' => 'collection', 'label' => 'Shortcut Items',
            'required' => true, 'min_items' => 1, 'max_items' => 6,
            'fields' => [
                [
                    'name' => 'label', 'type' => 'text', 'label' => 'Shortcut Label',
                    'required' => true, 'max_length' => 120,
                ],
                [
                    'name' => 'description', 'type' => 'textarea', 'label' => 'Description',
                    'required' => true, 'max_length' => 500,
                ],
                [
                    'name' => 'url', 'type' => 'url', 'label' => 'Shortcut URL',
                    'required' => true, 'max_length' => 2048,
                ],
                [
                    'name' => 'icon', 'type' => 'select', 'label' => 'Icon', 'default' => 'circle_user',
                    'options' => $this->options([
                        'circle_user' => 'Account',
                        'message_square' => 'Message',
                        'mail' => 'Mail',
                        'phone' => 'Phone',
                        'map_pin' => 'Location',
                        'circle_help' => 'Help',
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
                ['name' => 'open_in_new', 'type' => 'yesno', 'label' => 'Open in New Tab', 'default' => false],
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
