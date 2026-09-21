<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Address\Mapping;

use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Address\Mapping\NameNormalizer;

/**
 * TASK-MZ2TCB / AC-B5 — deterministic normalization: NFC, trim/collapse whitespace, lowercase,
 * separator punctuation stripped, Vietnamese diacritics PRESERVED.
 */
class NameNormalizerTest extends TestCase
{
    private NameNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new NameNormalizer();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function provider(): array
    {
        return [
            'collapses whitespace and lowercases' => ['  Tp.  Hồ  Chí  Minh ', 'tp hồ chí minh'],
            'strips separators' => ['Quận 1 - Bến Nghé (Ward)', 'quận 1 bến nghé ward'],
            'strips apostrophes and slashes' => ["Xã Phú An/Kinh", 'xã phú ankinh'],
            'keeps diacritics' => ['Huyện Cù Lao Dung', 'huyện cù lao dung'],
            'decomposable form becomes composed then lowered' => ["Th\xE1\xBB\x8B tr\xE1\xBA\xA5n", 'thị trấn'],
            'empty stays empty' => ['   ', ''],
        ];
    }

    /**
     * @dataProvider provider
     */
    public function testNormalize(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize($input));
    }

    public function testEqualAfterNormalization(): void
    {
        $this->assertSame(
            $this->normalizer->normalize('Tp. Hồ Chí Minh'),
            $this->normalizer->normalize('TP HỒ CHÍ MINH')
        );
    }
}
