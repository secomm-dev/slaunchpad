<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\BannerBDefinition;

class BannerBDefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new BannerBDefinition();

        self::assertSame('banner_b', $definition->getId());
        self::assertSame('Split Banner', $definition->getLabel());
        self::assertSame('Secomm_UiWidget::components/banner/b.phtml', $definition->getTemplate());
        self::assertSame('banner/B-split', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testSchemaRequiresMediaAndUsesAllowlistedLayoutOptions(): void
    {
        $fields = [];
        foreach ((new BannerBDefinition())->getFields() as $field) {
            $fields[$field['name']] = $field;
        }

        self::assertTrue($fields['title']['required']);
        self::assertTrue($fields['mobile_image']['required']);
        self::assertSame('media-image', $fields['mobile_image']['type']);
        self::assertSame('media-image', $fields['desktop_image']['type']);
        self::assertTrue($fields['image_alt']['required']);
        self::assertSame('trusted-rich-text', $fields['content']['type']);
        self::assertSame(['left', 'right'], array_column($fields['image_side']['options'], 'value'));
        self::assertSame(['cta_url'], $fields['cta_label']['required_with']);
    }
}
