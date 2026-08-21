<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

declare(strict_types=1);

namespace Mageplaza\ExtraFee\Test\Unit\Model\Config\Source;

use Mageplaza\ExtraFee\Model\Config\Source\CalculateOptions;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the CalculateOptions source whose constants drive what a percentage fee is
 * calculated on (discount / shipping / tax) inside the Quote total model.
 *
 * @covers \Mageplaza\ExtraFee\Model\Config\Source\CalculateOptions
 * @covers \Mageplaza\ExtraFee\Model\Config\AbstractSource
 */
class CalculateOptionsTest extends TestCase
{
    private CalculateOptions $model;

    protected function setUp(): void
    {
        $this->model = new CalculateOptions();
    }

    /**
     * Happy path: the constants are the exact ids stored in the rule config and later
     * compared with in_array() during fee calculation.
     */
    public function testConstantsHaveStableValues(): void
    {
        $this->assertSame(1, CalculateOptions::DISCOUNT);
        $this->assertSame(2, CalculateOptions::SHIPPING_FEE);
        $this->assertSame(3, CalculateOptions::TAX);
    }

    /**
     * Happy path: toArray() exposes discount, shipping fee and tax.
     */
    public function testToArrayContainsAllCalculateOptions(): void
    {
        $array = $this->model->toArray();

        $this->assertCount(3, $array);
        $this->assertArrayHasKey(CalculateOptions::DISCOUNT, $array);
        $this->assertArrayHasKey(CalculateOptions::SHIPPING_FEE, $array);
        $this->assertArrayHasKey(CalculateOptions::TAX, $array);
    }

    /**
     * Happy path: option array carries value/label pairs for the UI multiselect.
     */
    public function testToOptionArrayProducesValueLabelPairs(): void
    {
        $options = $this->model->toOptionArray();

        $this->assertCount(3, $options);
        $this->assertSame(['value', 'label'], array_keys($options[0]));
    }
}
