<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\CardBDefinition;

class CardBDefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new CardBDefinition();

        self::assertSame('card_b', $definition->getId());
        self::assertSame('Media Card', $definition->getLabel());
        self::assertSame('Secomm_UiWidget::components/card/b.phtml', $definition->getTemplate());
        self::assertSame('card/B-media', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testSchemaKeepsRequiredMediaAndOptionalParityExtension(): void
    {
        $fields = [];
        foreach ((new CardBDefinition())->getFields() as $field) {
            $fields[$field['name']] = $field;
        }

        self::assertTrue($fields['title']['required']);
        self::assertSame('trusted-rich-text', $fields['content']['type']);
        self::assertTrue($fields['image']['required']);
        self::assertSame('media-image', $fields['image']['type']);
        self::assertTrue($fields['image_alt']['required']);
        self::assertSame('left', $fields['image_position']['default']);
        self::assertSame(['left', 'right'], array_column($fields['image_position']['options'], 'value'));
        self::assertSame(['cta_url'], $fields['cta_label']['required_with']);
        self::assertSame(['button', 'primary', 'secondary'], array_column(
            $fields['link_appearance']['options'],
            'value'
        ));
    }
}
