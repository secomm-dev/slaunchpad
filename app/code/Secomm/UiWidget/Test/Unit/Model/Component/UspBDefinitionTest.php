<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\UspBDefinition;

class UspBDefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new UspBDefinition();

        self::assertSame('usp_b', $definition->getId());
        self::assertSame('Benefit Cards', $definition->getLabel());
        self::assertSame('Secomm_UiWidget::components/usp/b.phtml', $definition->getTemplate());
        self::assertSame('usp/B-cards', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testDefinesBoundedCardsAndCompoundCta(): void
    {
        $fields = array_column((new UspBDefinition())->getFields(), null, 'name');
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
        self::assertSame(['cta_url'], $itemFields['cta_label']['required_with']);
        self::assertSame(['cta_label'], $itemFields['cta_url']['required_with']);
    }
}
