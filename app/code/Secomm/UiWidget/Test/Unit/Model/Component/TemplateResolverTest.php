<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\Definition;
use Secomm\UiWidget\Model\Component\Registry;
use Secomm\UiWidget\Model\Component\TemplateResolver;

class TemplateResolverTest extends TestCase
{
    public function testResolvesOnlyRegisteredComponentTemplates(): void
    {
        $resolver = new TemplateResolver(new Registry([
            new Definition('banner_a', 'Banner A', 'Secomm_UiWidget::components/banner/a.phtml'),
        ]));

        self::assertSame(
            'Secomm_UiWidget::components/banner/a.phtml',
            $resolver->resolve('banner_a')
        );
        self::assertNull($resolver->resolve('../../vendor/template.phtml'));
    }
}
