<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\ShortcutsADefinition;

class ShortcutsADefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new ShortcutsADefinition();

        self::assertSame('shortcuts_a', $definition->getId());
        self::assertSame('Shortcut Links', $definition->getLabel());
        self::assertSame('Secomm_UiWidget::components/shortcuts/a.phtml', $definition->getTemplate());
        self::assertSame('shortcuts/A-simple', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testDefinesBoundedOrderedIconOrImageItems(): void
    {
        $fields = array_column((new ShortcutsADefinition())->getFields(), null, 'name');
        $itemFields = array_column($fields['items']['fields'], null, 'name');

        self::assertSame(1, $fields['items']['min_items']);
        self::assertSame(6, $fields['items']['max_items']);
        self::assertTrue($itemFields['label']['required']);
        self::assertTrue($itemFields['description']['required']);
        self::assertTrue($itemFields['url']['required']);
        self::assertSame('circle_user', $itemFields['icon']['default']);
        self::assertSame('media-image', $itemFields['image']['type']);
        self::assertSame(['image'], $itemFields['image_alt']['required_with']);
        self::assertSame(
            ['circle_user', 'message_square', 'mail', 'phone', 'map_pin', 'circle_help'],
            array_column($itemFields['icon']['options'], 'value')
        );
    }
}
