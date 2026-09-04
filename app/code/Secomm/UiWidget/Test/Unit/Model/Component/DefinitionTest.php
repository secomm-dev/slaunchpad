<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\Definition;

class DefinitionTest extends TestCase
{
    public function testAcceptsRegisteredMagentoTemplateAlias(): void
    {
        $definition = new Definition(
            'banner_a',
            'Banner A',
            'Secomm_UiWidget::components/banner/a.phtml',
            sourceComponent: 'banner/A-default',
            sourceVersion: '2.8.0'
        );

        self::assertSame('banner_a', $definition->getId());
        self::assertSame(1, $definition->getSchemaVersion());
        self::assertSame('banner/A-default', $definition->getSourceComponent());
    }

    /**
     * @dataProvider invalidDefinitionProvider
     */
    public function testRejectsInvalidDefinitions(string $id, string $label, string $template, int $version): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Definition($id, $label, $template, schemaVersion: $version);
    }

    public static function invalidDefinitionProvider(): array
    {
        return [
            'invalid ID' => ['Banner-A', 'Banner A', 'Secomm_UiWidget::banner/a.phtml', 1],
            'empty label' => ['banner_a', '', 'Secomm_UiWidget::banner/a.phtml', 1],
            'raw path' => ['banner_a', 'Banner A', '../../vendor/template.phtml', 1],
            'invalid version' => ['banner_a', 'Banner A', 'Secomm_UiWidget::banner/a.phtml', 0],
        ];
    }
}
