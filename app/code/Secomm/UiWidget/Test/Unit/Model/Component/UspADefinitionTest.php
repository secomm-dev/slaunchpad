<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\UspADefinition;

class UspADefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new UspADefinition();

        self::assertSame('usp_a', $definition->getId());
        self::assertSame('Icon Benefits', $definition->getLabel());
        self::assertSame('Secomm_UiWidget::components/usp/a.phtml', $definition->getTemplate());
        self::assertSame('usp/A-icons', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testDefinesBoundedBenefitsAndAllowlistedMedia(): void
    {
        $fields = array_column((new UspADefinition())->getFields(), null, 'name');
        $itemFields = array_column($fields['items']['fields'], null, 'name');

        self::assertTrue($fields['eyebrow']['required']);
        self::assertTrue($fields['heading']['required']);
        self::assertSame(2, $fields['items']['min_items']);
        self::assertSame(8, $fields['items']['max_items']);
        self::assertTrue($itemFields['title']['required']);
        self::assertTrue($itemFields['text']['required']);
        self::assertSame(
            ['shield_check', 'truck', 'gift', 'receipt'],
            array_column($itemFields['icon']['options'], 'value')
        );
        self::assertSame('media-image', $itemFields['image']['type']);
        self::assertSame(['image'], $itemFields['image_alt']['required_with']);
        self::assertSame('url', $itemFields['url']['type']);
    }
}
