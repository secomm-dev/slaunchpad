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

namespace Mageplaza\Smtp\Test\Unit\Model\Config\Source;

use Mageplaza\Smtp\Model\Config\Source\SyncType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SyncType::class)]
class SyncTypeTest extends TestCase
{
    private SyncType $model;

    protected function setUp(): void
    {
        $this->model = new SyncType();
    }

    public function testConstantsHoldExpectedValues(): void
    {
        $this->assertSame('all', SyncType::ALL);
        $this->assertSame(1, SyncType::CUSTOMERS);
        $this->assertSame(2, SyncType::ORDERS);
        $this->assertSame(3, SyncType::SUBSCRIBERS);
    }

    public function testToOptionArrayEachOptionHasValueAndLabelKeys(): void
    {
        $result = $this->model->toOptionArray();

        $this->assertNotEmpty($result);
        foreach ($result as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
        }
    }

    public function testToOptionArrayReturnsFourItemsInCustomersOrdersSubscribersEverythingOrder(): void
    {
        $values = array_column($this->model->toOptionArray(), 'value');

        $this->assertSame(
            [SyncType::CUSTOMERS, SyncType::ORDERS, SyncType::SUBSCRIBERS, SyncType::ALL],
            $values
        );
    }
}
