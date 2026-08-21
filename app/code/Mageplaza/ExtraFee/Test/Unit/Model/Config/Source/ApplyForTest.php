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

use Mageplaza\ExtraFee\Model\Config\Source\ApplyFor;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the ApplyFor source (whole cart vs each product). CART=1 is the branch the
 * fee calculator uses to pick getFeeType() vs getFeeTypeItem().
 *
 * @covers \Mageplaza\ExtraFee\Model\Config\Source\ApplyFor
 * @covers \Mageplaza\ExtraFee\Model\Config\AbstractSource
 */
class ApplyForTest extends TestCase
{
    private ApplyFor $model;

    protected function setUp(): void
    {
        $this->model = new ApplyFor();
    }

    /**
     * Happy path: CART=1, ITEM=2.
     */
    public function testConstantsHaveStableValues(): void
    {
        $this->assertSame(1, ApplyFor::CART);
        $this->assertSame(2, ApplyFor::ITEM);
    }

    /**
     * Happy path: toArray()/toOptionArray() expose both apply-for targets.
     */
    public function testToOptionArrayContainsCartAndItem(): void
    {
        $this->assertCount(2, $this->model->toArray());

        $values = array_column($this->model->toOptionArray(), 'value');
        $this->assertEqualsCanonicalizing([ApplyFor::CART, ApplyFor::ITEM], $values);
    }
}
