<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Test\Unit\Hash;

use PHPUnit\Framework\TestCase;
use Secomm\Tracking\Model\Hash\UserDataHasher;

/**
 * TASK-NNKTRM AC-2 — E.164 VN normalization + SHA-256 output contract (SPEC §6.4).
 */
class UserDataHasherTest extends TestCase
{
    private UserDataHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new UserDataHasher();
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public function vnPhoneProvider(): array
    {
        return [
            'mobile trunk-zero' => ['0901234567', '+84901234567'],
            'mobile with spaces' => ['090 123 4567', '+84901234567'],
            'mobile with dashes' => ['090-123-4567', '+84901234567'],
            'mobile with dots parens' => ['(090).123.4567', '+84901234567'],
            'mobile already intl plus' => ['+84901234567', '+84901234567'],
            'mobile already intl no plus' => ['84901234567', '+84901234567'],
            'new-format mobile 03x' => ['0351234567', '+84351234567'],
            'landline HCM' => ['02838221234', '+842838221234'],
            'landline HN old prefix' => ['0438221234', '+84438221234'],
        ];
    }

    /**
     * @dataProvider vnPhoneProvider
     */
    public function testVnPhoneNormalizesToE164(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->hasher->normalizePhone($input, 'VN'));
        $hash = $this->hasher->hashPhone($input, 'VN');
        $this->assertNotNull($hash);
        $this->assertSame(hash('sha256', $expected), $hash);
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public function garbagePhoneProvider(): array
    {
        return [
            'empty' => ['', null],
            'only separators' => [' - . ', null],
            'only plus' => ['+', null],
            'too short' => ['12345', null],
            'letters only' => ['abc-defg', null],
            'null-ish' => [' ', null],
        ];
    }

    /**
     * @dataProvider garbagePhoneProvider
     */
    public function testGarbagePhoneYieldsNull(string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->hasher->hashPhone($input, 'VN'));
    }

    public function testInternationalPhoneWithoutCountryKeepsDigits(): void
    {
        // Not VN-shaped, no country context: leading + is preserved.
        $this->assertSame('+14155551234', $this->hasher->normalizePhone('+1 (415) 555-1234'));
    }

    public function testVnCountryContextRescuesLocalWithoutTrunkZero(): void
    {
        $this->assertSame('+84901234567', $this->hasher->normalizePhone('901234567', 'VN'));
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public function emailProvider(): array
    {
        return [
            'plain' => ['linh.bui@secomm.vn', 'linh.bui@secomm.vn'],
            'uppercase + spaces' => ['  LINH.BUI@SECOMM.VN ', 'linh.bui@secomm.vn'],
            'mixed case' => ['Linh.Bui@Secomm.vn', 'linh.bui@secomm.vn'],
            'invalid no at' => ['linh.secomm.vn', null],
            'invalid empty local' => ['@secomm.vn', null],
            'empty' => ['', null],
            'spaces only' => ['   ', null],
        ];
    }

    /**
     * @dataProvider emailProvider
     */
    public function testEmailNormalizeAndHash(string $input, ?string $expected): void
    {
        $hash = $this->hasher->hashEmail($input);
        if ($expected === null) {
            $this->assertNull($hash);
        } else {
            $this->assertSame(hash('sha256', $expected), $hash);
        }
    }

    public function testExternalIdHash(): void
    {
        $this->assertSame(hash('sha256', '42'), $this->hasher->hashExternalId(42));
        $this->assertSame(hash('sha256', '42'), $this->hasher->hashExternalId('42'));
        $this->assertNull($this->hasher->hashExternalId(''));
        $this->assertNull($this->hasher->hashExternalId(null));
    }

    public function testAllHashesAre64CharHex(): void
    {
        foreach ([
            $this->hasher->hashEmail('qc@launchpad.local'),
            $this->hasher->hashPhone('0901234567', 'VN'),
            $this->hasher->hashExternalId(7),
        ] as $hash) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string)$hash);
        }
    }
}
