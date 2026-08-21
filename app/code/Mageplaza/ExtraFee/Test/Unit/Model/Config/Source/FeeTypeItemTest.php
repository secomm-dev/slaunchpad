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

use Mageplaza\ExtraFee\Model\Config\Source\FeeTypeItem;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the per-item fee-type option source used by the item-based fee calculation.
 *
 * @covers \Mageplaza\ExtraFee\Model\Config\Source\FeeTypeItem
 * @covers \Mageplaza\ExtraFee\Model\Config\AbstractSource
 */
class FeeTypeItemTest extends TestCase
{
    private FeeTypeItem $model;

    protected function setUp(): void
    {
        $this->model = new FeeTypeItem();
    }

    /**
     * Happy path: item fee-type constants continue at 4/5 (distinct from cart FeeType 1-3).
     */
    public function testConstantsHaveStableValues(): void
    {
        $this->assertSame(4, FeeTypeItem::FIX_AMOUNT_FOR_ITEM);
        $this->assertSame(5, FeeTypeItem::PERCENTAGE_ITEM_AMOUNT);
    }

    /**
     * Happy path: toArray() exposes exactly the two item fee types.
     */
    public function testToArrayContainsBothItemFeeTypes(): void
    {
        $array = $this->model->toArray();

        $this->assertCount(2, $array);
        $this->assertArrayHasKey(FeeTypeItem::FIX_AMOUNT_FOR_ITEM, $array);
        $this->assertArrayHasKey(FeeTypeItem::PERCENTAGE_ITEM_AMOUNT, $array);
    }

    /**
     * Happy path: toOptionArray() yields value/label pairs for both entries.
     */
    public function testToOptionArrayProducesValueLabelPairs(): void
    {
        $options = $this->model->toOptionArray();

        $this->assertCount(2, $options);
        $this->assertSame(['value', 'label'], array_keys($options[0]));
    }
}
