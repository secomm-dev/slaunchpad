<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Content;

use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Filter\Template;
use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Content\TrustedHtmlRenderer;

class TrustedHtmlRendererTest extends TestCase
{
    public function testDelegatesToMagentoCmsBlockFilter(): void
    {
        $filter = $this->createMock(Template::class);
        $filter->expects(self::once())->method('filter')->with('<p>{{store url=""}}</p>')->willReturn('<p>/</p>');
        $provider = $this->createMock(FilterProvider::class);
        $provider->expects(self::once())->method('getBlockFilter')->willReturn($filter);

        self::assertSame(
            '<p>/</p>',
            (new TrustedHtmlRenderer($provider))->render('<p>{{store url=""}}</p>')
        );
    }
}
