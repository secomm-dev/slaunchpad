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

use Mageplaza\Smtp\Model\Config\Source\Protocol;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Protocol::class)]
class ProtocolTest extends TestCase
{
    private Protocol $model;

    protected function setUp(): void
    {
        $this->model = new Protocol();
    }

    public function testToOptionArrayReturnsThreeOptions(): void
    {
        $this->assertCount(3, $this->model->toOptionArray());
    }

    public function testToOptionArrayValuesInOrder(): void
    {
        $values = array_column($this->model->toOptionArray(), 'value');

        $this->assertSame(['', 'ssl', 'tls'], $values);
    }

    public function testToOptionArrayLabelsMatchExpectedPhrases(): void
    {
        $labels = array_column($this->model->toOptionArray(), 'label');

        $this->assertEquals([__('None'), __('SSL'), __('TLS')], $labels);
    }
}
