<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\CategoriesADefinition;

class CategoriesADefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new CategoriesADefinition();

        self::assertSame('categories_a', $definition->getId());
        self::assertSame('Image Category Grid', $definition->getLabel());
        self::assertSame('Secomm_UiWidget::components/categories/a.phtml', $definition->getTemplate());
        self::assertSame('categories/A-grid-images', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testDefinesBoundedOrderedGalleryItems(): void
    {
        $fields = array_column((new CategoriesADefinition())->getFields(), null, 'name');
        $itemFields = array_column($fields['items']['fields'], null, 'name');

        self::assertTrue($fields['heading']['required']);
        self::assertTrue($fields['mobile_slider']['default']);
        self::assertSame(1, $fields['items']['min_items']);
        self::assertSame(12, $fields['items']['max_items']);
        self::assertTrue($itemFields['label']['required']);
        self::assertTrue($itemFields['image']['required']);
        self::assertSame('media-image', $itemFields['image']['type']);
        self::assertTrue($itemFields['image_alt']['required']);
        self::assertTrue($itemFields['url']['required']);
        self::assertSame(['browse_url'], $fields['browse_label']['required_with']);
    }
}
