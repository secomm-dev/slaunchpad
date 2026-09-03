<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\GenericContentADefinition;

class GenericContentADefinitionTest extends TestCase
{
    public function testDefinesUpstreamContentSlotsWithoutLayoutOverrides(): void
    {
        $definition = new GenericContentADefinition();
        $fields = array_column($definition->getFields(), null, 'name');

        self::assertSame('generic_content_a', $definition->getId());
        self::assertSame('Rich Text Content', $definition->getLabel());
        self::assertSame('generic-content/A-text', $definition->getSourceComponent());
        self::assertSame('trusted-rich-text', $fields['content']['type']);
        self::assertTrue($fields['content']['required']);
        self::assertArrayHasKey('eyebrow', $fields);
        self::assertArrayHasKey('heading', $fields);
        self::assertArrayHasKey('lead', $fields);
        self::assertArrayNotHasKey('alignment', $fields);
        self::assertArrayNotHasKey('width', $fields);
    }
}
