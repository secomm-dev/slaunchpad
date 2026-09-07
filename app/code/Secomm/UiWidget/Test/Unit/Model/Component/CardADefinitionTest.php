<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\CardADefinition;

class CardADefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new CardADefinition();

        self::assertSame('card_a', $definition->getId());
        self::assertSame('Feature Card', $definition->getLabel());
        self::assertSame('Secomm_UiWidget::components/card/a.phtml', $definition->getTemplate());
        self::assertSame('card/A-default', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testSchemaKeepsRequiredMediaAndTrustedContent(): void
    {
        $fields = [];
        foreach ((new CardADefinition())->getFields() as $field) {
            $fields[$field['name']] = $field;
        }

        self::assertTrue($fields['title']['required']);
        self::assertSame('trusted-rich-text', $fields['content']['type']);
        self::assertTrue($fields['mobile_image']['required']);
        self::assertSame('media-image', $fields['mobile_image']['type']);
        self::assertSame('media-image', $fields['desktop_image']['type']);
        self::assertTrue($fields['image_alt']['required']);
        self::assertSame(['cta_url'], $fields['cta_label']['required_with']);
        self::assertSame(['button', 'primary', 'secondary'], array_column(
            $fields['link_appearance']['options'],
            'value'
        ));
    }
}
