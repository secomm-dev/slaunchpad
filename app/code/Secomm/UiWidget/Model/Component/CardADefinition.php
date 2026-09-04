<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

/**
 * Schema and provenance for the Card A default component.
 */
class CardADefinition extends Definition
{
    public function __construct()
    {
        parent::__construct(
            id: 'card_a',
            label: 'Feature Card',
            template: 'Secomm_UiWidget::components/card/a.phtml',
            group: 'content',
            schemaVersion: 1,
            fields: $this->getFieldSchema(),
            sourceComponent: 'card/A-default',
            sourceVersion: '2.8.0',
            sortOrder: 20
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getFieldSchema(): array
    {
        return [
            ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true, 'max_length' => 120],
            [
                'name' => 'content', 'type' => 'trusted-rich-text', 'label' => 'Content',
                'max_length' => 12000, 'editor_height' => 320,
                'description' => 'Trusted CMS content. Magento CMS directives are supported.',
            ],
            [
                'name' => 'mobile_image', 'type' => 'media-image', 'label' => 'Mobile Image',
                'required' => true, 'max_length' => 2048,
                'description' => 'Used on mobile and as the desktop fallback.',
            ],
            ['name' => 'desktop_image', 'type' => 'media-image', 'label' => 'Desktop Image', 'max_length' => 2048],
            ['name' => 'image_alt', 'type' => 'text', 'label' => 'Image Alt Text', 'required' => true, 'max_length' => 255],
            [
                'name' => 'loading', 'type' => 'select', 'label' => 'Image Loading', 'default' => 'lazy',
                'options' => $this->options(['lazy' => 'Lazy', 'eager' => 'Eager']),
            ],
            [
                'name' => 'cta_label', 'type' => 'text', 'label' => 'CTA Label',
                'max_length' => 80, 'required_with' => ['cta_url'],
            ],
            [
                'name' => 'cta_url', 'type' => 'url', 'label' => 'CTA URL',
                'max_length' => 2048, 'required_with' => ['cta_label'],
            ],
            ['name' => 'open_in_new', 'type' => 'yesno', 'label' => 'Open CTA in New Tab', 'default' => false],
            [
                'name' => 'link_appearance', 'type' => 'select', 'label' => 'CTA Appearance', 'default' => 'button',
                'options' => $this->options([
                    'button' => 'Neutral Button', 'primary' => 'Primary Button', 'secondary' => 'Secondary Button',
                ]),
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
