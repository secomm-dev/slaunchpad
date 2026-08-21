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
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */
declare(strict_types=1);

namespace Mageplaza\Smtp\Test\Unit\Model\Source;

use Mageplaza\Smtp\Model\Source\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Status::class)]
class StatusTest extends TestCase
{
    private Status $model;

    protected function setUp(): void
    {
        $this->model = new Status();
    }

    public function testConstants(): void
    {
        $this->assertSame(1, Status::STATUS_SUCCESS);
        $this->assertSame(0, Status::STATUS_ERROR);
    }

    public function testToOptionArrayFormat(): void
    {
        $result = $this->model->toOptionArray();

        $this->assertNotEmpty($result);
        foreach ($result as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
        }
    }

    public function testToOptionArrayValues(): void
    {
        $values = array_column($this->model->toOptionArray(), 'value');

        $this->assertSame([Status::STATUS_SUCCESS, Status::STATUS_ERROR], $values);
    }

    public function testToOptionArrayLabels(): void
    {
        $labels = array_map('strval', array_column($this->model->toOptionArray(), 'label'));

        $this->assertSame(['Success', 'Error'], $labels);
    }
}
