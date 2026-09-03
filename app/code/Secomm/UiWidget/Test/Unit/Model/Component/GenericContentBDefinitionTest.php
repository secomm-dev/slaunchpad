<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\GenericContentBDefinition;

class GenericContentBDefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new GenericContentBDefinition();

        self::assertSame('generic_content_b', $definition->getId());
        self::assertSame('Visual Content', $definition->getLabel());
        self::assertSame('Secomm_UiWidget::components/generic-content/b.phtml', $definition->getTemplate());
        self::assertSame('generic-content/B-visual', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testDefinesRequiredVisualContentAndOptInExtensions(): void
    {
        $fields = array_column((new GenericContentBDefinition())->getFields(), null, 'name');

        self::assertTrue($fields['eyebrow']['required']);
        self::assertTrue($fields['heading']['required']);
        self::assertTrue($fields['lead']['required']);
        self::assertSame('trusted-rich-text', $fields['content']['type']);
        self::assertTrue($fields['content']['required']);
        self::assertSame('media-image', $fields['image']['type']);
        self::assertTrue($fields['image']['required']);
        self::assertSame('left', $fields['image_position']['default']);
        self::assertSame(['cta_url'], $fields['cta_label']['required_with']);
        self::assertSame(['button', 'primary', 'secondary'], array_column(
            $fields['link_appearance']['options'],
            'value'
        ));
    }
}
