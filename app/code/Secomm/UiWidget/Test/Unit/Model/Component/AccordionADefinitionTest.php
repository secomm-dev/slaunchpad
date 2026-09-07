<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\AccordionADefinition;

class AccordionADefinitionTest extends TestCase
{
    public function testDefinesBoundedOrderedTrustedPanels(): void
    {
        $definition = new AccordionADefinition();
        $fields = array_column($definition->getFields(), null, 'name');
        $panelFields = array_column($fields['panels']['fields'], null, 'name');

        self::assertSame('accordion_a', $definition->getId());
        self::assertSame('Accordion', $definition->getLabel());
        self::assertSame('accordion/A-basic', $definition->getSourceComponent());
        self::assertArrayNotHasKey('heading', $fields);
        self::assertSame(1, $fields['panels']['min_items']);
        self::assertSame(12, $fields['panels']['max_items']);
        self::assertTrue($panelFields['title']['required']);
        self::assertSame('trusted-rich-text', $panelFields['content']['type']);
        self::assertTrue($panelFields['content']['required']);
        self::assertSame(440, $panelFields['content']['editor_height']);
        self::assertFalse($panelFields['open']['default']);
    }
}
