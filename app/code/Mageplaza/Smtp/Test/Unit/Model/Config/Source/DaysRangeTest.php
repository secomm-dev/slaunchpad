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

use Mageplaza\Smtp\Model\Config\Source\DaysRange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DaysRange::class)]
class DaysRangeTest extends TestCase
{
    private DaysRange $model;

    protected function setUp(): void
    {
        $this->model = new DaysRange();
    }

    public function testToArrayReturnsKeyedRangeOptions(): void
    {
        // PHP casts numeric string array keys ('90') to integers.
        $this->assertSame(['lifetime', 90, 365, 730, DaysRange::CUSTOM], array_keys($this->model->toArray()));
    }

    public function testToOptionArrayMirrorsToArray(): void
    {
        $array   = $this->model->toArray();
        $options = $this->model->toOptionArray();

        $this->assertCount(5, $options);

        foreach ($options as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
            $this->assertArrayHasKey($option['value'], $array);
            // __() returns a fresh Phrase instance each call — compare the rendered strings.
            $this->assertSame((string) $array[$option['value']], (string) $option['label']);
        }
    }

    public function testToOptionArrayIncludesCustomRange(): void
    {
        $this->assertContains(DaysRange::CUSTOM, array_column($this->model->toOptionArray(), 'value'));
    }
}
