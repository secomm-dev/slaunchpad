<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\EmbedADefinition;

class EmbedADefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new EmbedADefinition();

        self::assertSame('embed_a', $definition->getId());
        self::assertSame('Video Embed', $definition->getLabel());
        self::assertSame('Secomm_UiWidget::components/embed/a.phtml', $definition->getTemplate());
        self::assertSame('embed/A-basic', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testDefinesProviderValidatedPrivacySchema(): void
    {
        $fields = array_column((new EmbedADefinition())->getFields(), null, 'name');

        self::assertSame(['youtube', 'vimeo'], array_column($fields['provider']['options'], 'value'));
        self::assertSame('video-url', $fields['video_url']['type']);
        self::assertSame('provider', $fields['video_url']['provider_field']);
        self::assertTrue($fields['video_url']['required']);
        self::assertTrue($fields['title']['required']);
        self::assertSame('auto', $fields['loading']['default']);
        self::assertSame('media-image', $fields['poster']['type']);
        self::assertTrue($fields['enhanced_privacy']['default']);
        self::assertSame(['16/9', '4/3', '1/1', '21/9'], array_column(
            $fields['aspect_ratio']['options'],
            'value'
        ));
    }
}
