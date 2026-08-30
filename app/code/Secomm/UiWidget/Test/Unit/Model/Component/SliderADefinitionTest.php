<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\SliderADefinition;

class SliderADefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new SliderADefinition();

        self::assertSame('slider_a', $definition->getId());
        self::assertSame('Content Slider', $definition->getLabel());
        self::assertSame('Secomm_UiWidget::components/slider/a.phtml', $definition->getTemplate());
        self::assertSame('slider/A-basic', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testDefinesBoundedSlidesAndUpstreamControls(): void
    {
        $fields = array_column((new SliderADefinition())->getFields(), null, 'name');
        $slideFields = array_column($fields['slides']['fields'], null, 'name');

        self::assertTrue($fields['label']['required']);
        self::assertSame('end', $fields['show_arrows']['default']);
        self::assertTrue($fields['show_dots']['default']);
        self::assertFalse($fields['load_first_eager']['default']);
        self::assertSame(2, $fields['slides']['min_items']);
        self::assertSame(12, $fields['slides']['max_items']);
        self::assertSame('media-image', $slideFields['image']['type']);
        self::assertTrue($slideFields['image']['required']);
        self::assertTrue($slideFields['image_alt']['required']);
        self::assertSame(['cta_url'], $slideFields['cta_label']['required_with']);
        self::assertSame(['cta_label'], $slideFields['cta_url']['required_with']);
    }
}
