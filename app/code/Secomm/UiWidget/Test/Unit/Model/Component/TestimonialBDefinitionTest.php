<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\UiWidget\Test\Unit\Model\Component;

use PHPUnit\Framework\TestCase;
use Secomm\UiWidget\Model\Component\TestimonialBDefinition;

class TestimonialBDefinitionTest extends TestCase
{
    public function testDefinesStableContractAndProvenance(): void
    {
        $definition = new TestimonialBDefinition();

        self::assertSame('testimonial_b', $definition->getId());
        self::assertSame('Testimonial Card', $definition->getLabel());
        self::assertSame('Secomm_UiWidget::components/testimonial/b.phtml', $definition->getTemplate());
        self::assertSame('testimonial/B-card', $definition->getSourceComponent());
        self::assertSame('2.8.0', $definition->getSourceVersion());
    }

    public function testDefinesRequiredQuoteAndOptionalCardDetails(): void
    {
        $fields = array_column((new TestimonialBDefinition())->getFields(), null, 'name');

        self::assertTrue($fields['quote']['required']);
        self::assertSame('textarea', $fields['quote']['type']);
        self::assertTrue($fields['author']['required']);
        self::assertSame('media-image', $fields['avatar']['type']);
        self::assertSame(['avatar'], $fields['avatar_alt']['required_with']);
        self::assertSame('0', $fields['rating']['default']);
        self::assertSame(['0', '1', '2', '3', '4', '5'], array_column($fields['rating']['options'], 'value'));
        self::assertSame('lazy', $fields['loading']['default']);
    }
}
