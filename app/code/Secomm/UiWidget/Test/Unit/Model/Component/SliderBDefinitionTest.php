<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\SliderBDefinition;

class SliderBDefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new SliderBDefinition();

        self::assertSame('slider_b', $definition->getId());
        self::assertSame('Logo Marquee', $definition->getLabel());
        self::assertSame('Secomm_UiWidget::components/slider/b.phtml', $definition->getTemplate());
        self::assertSame('slider/B-marquee', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testDefinesBoundedLogosAndAccessibilityControls(): void
    {
        $fields = array_column((new SliderBDefinition())->getFields(), null, 'name');
        $itemFields = array_column($fields['items']['fields'], null, 'name');

        self::assertTrue($fields['heading']['required']);
        self::assertTrue($fields['show_title']['default']);
        self::assertSame('normal', $fields['speed']['default']);
        self::assertSame('left', $fields['direction']['default']);
        self::assertTrue($fields['pause_on_interaction']['default']);
        self::assertSame(3, $fields['items']['min_items']);
        self::assertSame(12, $fields['items']['max_items']);
        self::assertSame('media-image', $itemFields['image']['type']);
        self::assertTrue($itemFields['image']['required']);
        self::assertTrue($itemFields['image_alt']['required']);
        self::assertSame(270, $itemFields['width']['default']);
        self::assertSame(95, $itemFields['height']['default']);
    }
}
