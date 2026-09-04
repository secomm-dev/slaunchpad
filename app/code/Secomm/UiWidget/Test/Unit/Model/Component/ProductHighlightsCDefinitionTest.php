<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\ProductHighlightsCDefinition;

class ProductHighlightsCDefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new ProductHighlightsCDefinition();

        self::assertSame('product_highlights_c', $definition->getId());
        self::assertSame('Product Highlights', $definition->getLabel());
        self::assertSame('Secomm_UiWidget::components/product-highlights/c.phtml', $definition->getTemplate());
        self::assertSame('product-data/C-highlights', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testDefinesBoundedOrderedContentOnlyHighlights(): void
    {
        $fields = array_column((new ProductHighlightsCDefinition())->getFields(), null, 'name');
        $itemFields = array_column($fields['highlights']['fields'], null, 'name');

        self::assertSame(1, $fields['highlights']['min_items']);
        self::assertSame(12, $fields['highlights']['max_items']);
        self::assertTrue($itemFields['title']['required']);
        self::assertSame('trusted-rich-text', $itemFields['content']['type']);
        self::assertTrue($itemFields['content']['required']);
        self::assertSame('media-image', $itemFields['image']['type']);
        self::assertTrue($itemFields['image']['required']);
        self::assertTrue($itemFields['image_alt']['required']);
        self::assertSame('left', $itemFields['image_position']['default']);
        self::assertSame(['left', 'right'], array_column($itemFields['image_position']['options'], 'value'));
    }
}
