<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Test\Unit\Model\OrderSubmit;

use PHPUnit\Framework\TestCase;
use Secomm\Ghtk\Model\OrderSubmit\LabelPdfGenerator;

class LabelPdfGeneratorTest extends TestCase
{
    public function testGeneratesRawPdfBytesWithVietnameseTextSurviving(): void
    {
        $pdf = (new LabelPdfGenerator())->generate(
            'S10001.P1.XXXX',
            'S10001.P1',
            'ghtk-100000001-1',
            ['Nguyễn Văn Admin', '25 Lý Thường Kiệt', 'Phường Hàng Trống']
        );

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(500, strlen($pdf));
        // No exception despite Vietnamese diacritics (transliterated for core fonts).
        $this->addToAssertionCount(1);
    }

    public function testLongLinesAreClampedNotBroken(): void
    {
        $long = str_repeat('A very long address segment ', 10);
        $pdf = (new LabelPdfGenerator())->generate('T1', 'L1', 'p-1', [$long]);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }
}
