<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

/**
 * Schema and provenance for the Accordion A component.
 */
class AccordionADefinition extends Definition
{
    /**
     * Initialize the stable Accordion A definition.
     */
    public function __construct()
    {
        parent::__construct(
            id: 'accordion_a',
            label: 'Accordion',
            template: 'Secomm_UiWidget::components/accordion/a.phtml',
            group: 'content',
            schemaVersion: 1,
            fields: [
                [
                    'name' => 'allow_multiple', 'type' => 'yesno',
                    'label' => 'Allow Multiple Open Panels', 'default' => false,
                ],
                [
                    'name' => 'show_dividers', 'type' => 'yesno',
                    'label' => 'Show Dividers', 'default' => true,
                ],
                [
                    'name' => 'panels', 'type' => 'collection', 'label' => 'Panels',
                    'required' => true, 'min_items' => 1, 'max_items' => 12,
                    'fields' => [
                        [
                            'name' => 'title', 'type' => 'text', 'label' => 'Panel Title',
                            'required' => true, 'max_length' => 160,
                        ],
                        [
                            'name' => 'content', 'type' => 'trusted-rich-text', 'label' => 'Panel Content',
                            'required' => true, 'max_length' => 12000, 'editor_height' => 440,
                            'description' => 'Trusted CMS content. Magento CMS directives are supported.',
                        ],
                        [
                            'name' => 'open', 'type' => 'yesno',
                            'label' => 'Open by Default', 'default' => false,
                        ],
                    ],
                ],
            ],
            sourceComponent: 'accordion/A-basic',
            sourceVersion: '2.8.0',
            sortOrder: 20
        );
    }
}
