<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\UspCDefinition;

class UspCDefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new UspCDefinition();

        self::assertSame('usp_c', $definition->getId());
        self::assertSame('Compact Benefits', $definition->getLabel());
        self::assertSame('Secomm_UiWidget::components/usp/c.phtml', $definition->getTemplate());
        self::assertSame('usp/C-compact', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testDefinesBoundedCompactItemsAndAllowlistedIcons(): void
    {
        $fields = array_column((new UspCDefinition())->getFields(), null, 'name');
        $itemFields = array_column($fields['items']['fields'], null, 'name');

        self::assertSame(2, $fields['items']['min_items']);
        self::assertSame(6, $fields['items']['max_items']);
        self::assertTrue($itemFields['label']['required']);
        self::assertTrue($itemFields['text']['required']);
        self::assertSame('shield_check', $itemFields['icon']['default']);
        self::assertSame(
            ['shield_check', 'truck', 'gift', 'receipt'],
            array_column($itemFields['icon']['options'], 'value')
        );
    }
}
