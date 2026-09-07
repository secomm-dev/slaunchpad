<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\ModalADefinition;

class ModalADefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new ModalADefinition();

        self::assertSame('modal_a', $definition->getId());
        self::assertSame('Information Modal', $definition->getLabel());
        self::assertSame('Secomm_UiWidget::components/modal/a.phtml', $definition->getTemplate());
        self::assertSame('modal/A-simple', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testDefinesRequiredDynamicContentAndActions(): void
    {
        $fields = array_column((new ModalADefinition())->getFields(), null, 'name');

        self::assertTrue($fields['trigger_label']['required']);
        self::assertTrue($fields['title']['required']);
        self::assertSame('trusted-rich-text', $fields['content']['type']);
        self::assertTrue($fields['content']['required']);
        self::assertTrue($fields['secondary_action_label']['required']);
        self::assertTrue($fields['primary_action_label']['required']);
    }
}
