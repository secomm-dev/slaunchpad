<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\BannerCDefinition;

class BannerCDefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new BannerCDefinition();

        self::assertSame('banner_c', $definition->getId());
        self::assertSame('Text Banner', $definition->getLabel());
        self::assertSame('Secomm_UiWidget::components/banner/c.phtml', $definition->getTemplate());
        self::assertSame('banner/C-text', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testSchemaUsesTrustedContentAndAllowlistedPresentation(): void
    {
        $fields = [];
        foreach ((new BannerCDefinition())->getFields() as $field) {
            $fields[$field['name']] = $field;
        }

        self::assertTrue($fields['title']['required']);
        self::assertSame('trusted-rich-text', $fields['content']['type']);
        self::assertSame(['cta_url'], $fields['cta_label']['required_with']);
        self::assertSame(['start', 'center', 'end'], array_column(
            $fields['content_alignment']['options'],
            'value'
        ));
        self::assertSame(['narrow', 'medium', 'wide'], array_column(
            $fields['content_width']['options'],
            'value'
        ));
    }
}
