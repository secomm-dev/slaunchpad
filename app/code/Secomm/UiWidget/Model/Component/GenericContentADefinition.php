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
            label: 'Rich Text Content',
            template: 'Secomm_UiWidget::components/generic-content/a.phtml',
            group: 'content',
            schemaVersion: 1,
            fields: [
                ['name' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow', 'max_length' => 80],
                ['name' => 'heading', 'type' => 'text', 'label' => 'Heading', 'max_length' => 160],
                [
                    'name' => 'lead', 'type' => 'textarea', 'label' => 'Lead',
                    'max_length' => 1000,
                ],
                [
                    'name' => 'content', 'type' => 'trusted-rich-text', 'label' => 'Content',
                    'required' => true, 'max_length' => 12000,
                    'description' => 'Trusted CMS content. Magento CMS directives are supported.',
                ],
            ],
            sourceComponent: 'generic-content/A-text',
            sourceVersion: '2.8.0',
            sortOrder: 40
        );
    }
}
