<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\BannerADefinition;

class BannerADefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new BannerADefinition();

        self::assertSame('banner_a', $definition->getId());
        self::assertSame(1, $definition->getSchemaVersion());
        self::assertSame('Secomm_UiWidget::components/banner/a.phtml', $definition->getTemplate());
        self::assertSame('banner/A-default', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testSchemaContainsRequiredMediaAndSafePresentationEnums(): void
    {
        $fields = [];
        foreach ((new BannerADefinition())->getFields() as $field) {
            $fields[$field['name']] = $field;
        }

        self::assertTrue($fields['title']['required']);
        self::assertTrue($fields['mobile_image']['required']);
        self::assertTrue($fields['image_alt']['required']);
        self::assertSame(['cta_url'], $fields['cta_label']['required_with']);
        self::assertSame(
            ['dark', 'light', 'brand'],
            array_column($fields['background_tone']['options'], 'value')
        );
        self::assertSame(
            ['primary', 'secondary', 'button', 'link', 'overlay'],
            array_column($fields['link_appearance']['options'], 'value')
        );
    }
}
