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
            '13 digits (84 + 11) — QC case 01/10' => ['8498578454444', null],
            'mobile with 10 digits after +84 — extra digit (QC 01/10)' => ['+849857845415', null],
            'local mobile with 11 digits — extra digit (QC 01/10)' => ['09857845415', null],
            'landline HCM 28 (9 digits, starts 2)' => ['+84281234567', '+84281234567'],
            'landline HN 24 (10 digits, starts 2)' => ['+842412345678', '+842412345678'],
            '+84 + 10 mobile digits — too long' => ['+849012345678', null],
            '+84 + 8 digits — too short' => ['+8412345678', null],
            'other country number' => ['12025550123', null],
            'bare zero' => ['0', null],
        ];
    }
}
