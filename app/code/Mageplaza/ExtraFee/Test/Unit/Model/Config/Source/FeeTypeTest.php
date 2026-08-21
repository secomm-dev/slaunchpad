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

use Mageplaza\ExtraFee\Model\Config\Source\FeeType;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the FeeType option source: constant values and the value/label option array
 * (logic inherited from AbstractSource::toOptionArray()).
 *
 * @covers \Mageplaza\ExtraFee\Model\Config\Source\FeeType
 * @covers \Mageplaza\ExtraFee\Model\Config\AbstractSource
 */
class FeeTypeTest extends TestCase
{
    private FeeType $model;

    protected function setUp(): void
    {
        $this->model = new FeeType();
    }

    /**
     * Happy path: the three fee-type constants keep their stable integer values
     * (other parts of the module compare against them, so they must not drift).
     */
    public function testConstantsHaveStableValues(): void
    {
        $this->assertSame(1, FeeType::FIX_AMOUNT_FOR_EACH_ITEM);
        $this->assertSame(2, FeeType::FIX_AMOUNT_FOR_WHOLE_CART);
        $this->assertSame(3, FeeType::PERCENTAGE_OF_CART_TOTAL);
    }

    /**
     * Happy path: toArray() maps every constant to a non-empty label.
     */
    public function testToArrayContainsAllFeeTypes(): void
    {
        $array = $this->model->toArray();

        $this->assertArrayHasKey(FeeType::FIX_AMOUNT_FOR_EACH_ITEM, $array);
        $this->assertArrayHasKey(FeeType::FIX_AMOUNT_FOR_WHOLE_CART, $array);
        $this->assertArrayHasKey(FeeType::PERCENTAGE_OF_CART_TOTAL, $array);
        $this->assertCount(3, $array);
    }

    /**
     * Happy path: toOptionArray() (AbstractSource) wraps each entry as value/label pairs.
     */
    public function testToOptionArrayProducesValueLabelPairs(): void
    {
        $options = $this->model->toOptionArray();

        $this->assertCount(3, $options);
        foreach ($options as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
        }
        $this->assertSame(FeeType::FIX_AMOUNT_FOR_EACH_ITEM, $options[0]['value']);
    }
}
