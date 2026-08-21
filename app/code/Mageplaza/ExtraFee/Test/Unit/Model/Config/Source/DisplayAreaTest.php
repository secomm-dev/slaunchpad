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

use Mageplaza\ExtraFee\Model\Config\Source\DisplayArea;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the DisplayArea source. The TOTAL constant exists for code use but is
 * intentionally NOT offered as a selectable option (edge case).
 *
 * @covers \Mageplaza\ExtraFee\Model\Config\Source\DisplayArea
 * @covers \Mageplaza\ExtraFee\Model\Config\AbstractSource
 */
class DisplayAreaTest extends TestCase
{
    private DisplayArea $model;

    protected function setUp(): void
    {
        $this->model = new DisplayArea();
    }

    /**
     * Happy path: display-area constants keep their ids used by setExtraFeeForItems().
     */
    public function testConstantsHaveStableValues(): void
    {
        $this->assertSame(1, DisplayArea::PAYMENT_METHOD);
        $this->assertSame(2, DisplayArea::SHIPPING_METHOD);
        $this->assertSame(3, DisplayArea::CART_SUMMARY);
        $this->assertSame(4, DisplayArea::TOTAL);
    }

    /**
     * Edge case: toArray() only lists the three selectable areas; TOTAL is excluded
     * from the option list even though the constant is defined.
     */
    public function testToArrayExcludesTotalArea(): void
    {
        $array = $this->model->toArray();

        $this->assertCount(3, $array);
        $this->assertArrayHasKey(DisplayArea::PAYMENT_METHOD, $array);
        $this->assertArrayHasKey(DisplayArea::SHIPPING_METHOD, $array);
        $this->assertArrayHasKey(DisplayArea::CART_SUMMARY, $array);
        $this->assertArrayNotHasKey(DisplayArea::TOTAL, $array);
    }
}
