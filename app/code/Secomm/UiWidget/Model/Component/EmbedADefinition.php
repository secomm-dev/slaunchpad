<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Model\Component;

/**
 * Schema and provenance for the Embed A video component.
 */
class EmbedADefinition extends Definition
{
    public function __construct()
    {
        parent::__construct(
            id: 'embed_a',
            label: 'Video Embed',
            template: 'Secomm_UiWidget::components/embed/a.phtml',
            group: 'content',
            schemaVersion: 1,
            fields: $this->getFieldSchema(),
            sourceComponent: 'embed/A-basic',
            sourceVersion: '2.8.0',
            sortOrder: 40
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getFieldSchema(): array
    {
        return [
            [
                'name' => 'provider', 'type' => 'select', 'label' => 'Video Provider', 'default' => 'youtube',
                'required' => true,
                'options' => $this->options(['youtube' => 'YouTube', 'vimeo' => 'Vimeo']),
            ],
            [
                'name' => 'video_url', 'type' => 'video-url', 'label' => 'Video URL',
                'required' => true, 'max_length' => 2048, 'provider_field' => 'provider',
                'description' => 'Enter a URL matching the selected YouTube or Vimeo provider.',
            ],
            ['name' => 'title', 'type' => 'text', 'label' => 'Accessible Video Title', 'required' => true, 'max_length' => 180],
            [
                'name' => 'loading', 'type' => 'select', 'label' => 'Video Loading', 'default' => 'auto',
                'options' => $this->options(['lazy' => 'Lazy', 'eager' => 'Eager', 'auto' => 'Poster with Play Button']),
            ],
            ['name' => 'poster', 'type' => 'media-image', 'label' => 'Poster Image', 'max_length' => 2048],
            ['name' => 'allow_autoplay', 'type' => 'yesno', 'label' => 'Allow Autoplay', 'default' => false],
            ['name' => 'enhanced_privacy', 'type' => 'yesno', 'label' => 'Enhanced Privacy', 'default' => true],
            [
                'name' => 'aspect_ratio', 'type' => 'select', 'label' => 'Aspect Ratio', 'default' => '16/9',
                'options' => $this->options(['16/9' => '16:9', '4/3' => '4:3', '1/1' => '1:1', '21/9' => '21:9']),
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
