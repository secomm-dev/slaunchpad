<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Test\Unit\Model\Phone;

use PHPUnit\Framework\TestCase;
use Secomm\CodRisk\Model\Phone\PhoneNormalizer;

/**
 * AC-002: different common VN input formats must map to ONE identity; invalid
 * input yields null (never a risk BLOCK — CR-008).
 */
class PhoneNormalizerTest extends TestCase
{
    private PhoneNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new PhoneNormalizer();
    }

    /**
     * @dataProvider phoneProvider
     */
    public function testNormalize(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize($input));
    }

    public static function phoneProvider(): array
    {
        return [
            'local with spaces' => ['0901 234 567', '+84901234567'],
            'country code plain' => ['84901234567', '+84901234567'],
            'plus country code spaced' => ['+84 901 234 567', '+84901234567'],
            'local compact' => ['0901234567', '+84901234567'],
            'dashes' => ['0901-234-567', '+84901234567'],
            'landline with area code' => ['0241234567', '+84241234567'],
            'already normalized' => ['+84901234567', '+84901234567'],
            'empty' => ['', null],
            'null' => [null, null],
            'too short' => ['09012', null],
            'letters' => ['0901abc234', null],
            'wrong subscriber length' => ['09012345678', null],
            'other country number' => ['12025550123', null],
            'bare zero' => ['0', null],
        ];
    }
}
