<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

/**
 * Schema and provenance for the Modal A component.
 */
class ModalADefinition extends Definition
{
    public function __construct()
    {
        parent::__construct(
            id: 'modal_a',
            label: 'Information Modal',
            template: 'Secomm_UiWidget::components/modal/a.phtml',
            group: 'content',
            schemaVersion: 1,
            fields: $this->getFieldSchema(),
            sourceComponent: 'modal/A-simple',
            sourceVersion: '2.8.0',
            sortOrder: 44
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getFieldSchema(): array
    {
        return [
            [
                'name' => 'trigger_label', 'type' => 'text', 'label' => 'Trigger Button Label',
                'required' => true, 'max_length' => 80,
            ],
            ['name' => 'title', 'type' => 'text', 'label' => 'Modal Title', 'required' => true, 'max_length' => 160],
            [
                'name' => 'content', 'type' => 'trusted-rich-text', 'label' => 'Modal Content',
                'required' => true, 'max_length' => 16000, 'editor_height' => 440,
                'description' => 'Trusted CMS content. Magento CMS directives are supported.',
            ],
            [
                'name' => 'secondary_action_label', 'type' => 'text', 'label' => 'Secondary Action Label',
                'required' => true, 'max_length' => 80,
            ],
            [
                'name' => 'primary_action_label', 'type' => 'text', 'label' => 'Primary Action Label',
                'required' => true, 'max_length' => 80,
            ],
        ];
    }
}
