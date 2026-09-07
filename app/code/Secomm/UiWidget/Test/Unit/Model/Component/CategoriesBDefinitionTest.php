<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\CategoriesBDefinition;

class CategoriesBDefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new CategoriesBDefinition();

        self::assertSame('categories_b', $definition->getId());
        self::assertSame('Pattern Category Grid', $definition->getLabel());
        self::assertSame('Secomm_UiWidget::components/categories/b.phtml', $definition->getTemplate());
        self::assertSame('categories/B-grid-patterns', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testDefinesBoundedOrderedPatternItems(): void
    {
        $fields = array_column((new CategoriesBDefinition())->getFields(), null, 'name');
        $itemFields = array_column($fields['items']['fields'], null, 'name');

        self::assertTrue($fields['heading']['required']);
        self::assertTrue($fields['mobile_slider']['default']);
        self::assertSame(1, $fields['items']['min_items']);
        self::assertSame(12, $fields['items']['max_items']);
        self::assertTrue($itemFields['label']['required']);
        self::assertTrue($itemFields['url']['required']);
        self::assertSame(['wiggle', 'bank_note'], array_column($itemFields['pattern']['options'], 'value'));
        self::assertSame(['pink', 'blue'], array_column($itemFields['background']['options'], 'value'));
        self::assertSame(['browse_url'], $fields['browse_label']['required_with']);
    }
}
