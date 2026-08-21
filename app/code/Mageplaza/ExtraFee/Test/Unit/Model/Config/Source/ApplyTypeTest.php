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

use Mageplaza\ExtraFee\Model\Config\Source\ApplyType;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the ApplyType source (Automatic vs Manual fee application).
 *
 * @covers \Mageplaza\ExtraFee\Model\Config\Source\ApplyType
 * @covers \Mageplaza\ExtraFee\Model\Config\AbstractSource
 */
class ApplyTypeTest extends TestCase
{
    private ApplyType $model;

    protected function setUp(): void
    {
        $this->model = new ApplyType();
    }

    /**
     * Happy path: AUTOMATIC=1, MANUAL=2 — values are persisted and compared elsewhere.
     */
    public function testConstantsHaveStableValues(): void
    {
        $this->assertSame(1, ApplyType::AUTOMATIC);
        $this->assertSame(2, ApplyType::MANUAL);
    }

    /**
     * Happy path: toArray()/toOptionArray() expose both apply types.
     */
    public function testToOptionArrayContainsBothApplyTypes(): void
    {
        $this->assertCount(2, $this->model->toArray());

        $values = array_column($this->model->toOptionArray(), 'value');
        $this->assertEqualsCanonicalizing([ApplyType::AUTOMATIC, ApplyType::MANUAL], $values);
    }
}
