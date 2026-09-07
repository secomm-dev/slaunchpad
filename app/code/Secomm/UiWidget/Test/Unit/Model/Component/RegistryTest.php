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
use Secomm\UiWidget\Model\Component\Registry;

class RegistryTest extends TestCase
{
    public function testReturnsEnabledDefinitionsInStableOrder(): void
    {
        $registry = new Registry([
            new Definition('banner_b', 'Banner B', 'Secomm_UiWidget::banner/b.phtml', sortOrder: 20),
            new Definition('banner_a', 'Banner A', 'Secomm_UiWidget::banner/a.phtml', sortOrder: 10),
            new Definition('disabled', 'Disabled', 'Secomm_UiWidget::disabled.phtml', enabled: false),
        ]);

        self::assertSame(['banner_a', 'banner_b'], array_map(
            static fn ($definition): string => $definition->getId(),
            $registry->getAll()
        ));
        self::assertNull($registry->get('disabled'));
        self::assertNull($registry->get('unknown'));
    }

    public function testRejectsDuplicateComponentIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate component ID: banner_a.');

        new Registry([
            new Definition('banner_a', 'Banner A', 'Secomm_UiWidget::banner/a.phtml'),
            new Definition('banner_a', 'Banner A duplicate', 'Secomm_UiWidget::banner/a-duplicate.phtml'),
        ]);
    }
}
