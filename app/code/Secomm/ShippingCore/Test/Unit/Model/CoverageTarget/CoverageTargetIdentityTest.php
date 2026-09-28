<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\CoverageTarget;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetIdentity;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetType;

/**
 * TASK-WY6WP5 — immutable (type, code) identity: factory validation, composite key,
 * equality. The logical coverage model key (directive §21) shared by P1 carriers and
 * future METHOD targets.
 */
class CoverageTargetIdentityTest extends TestCase
{
    public function testCarrierFactoryBuildsCarrierIdentity(): void
    {
        $identity = CoverageTargetIdentity::carrier('secomm_ghn');

        $this->assertSame(CoverageTargetType::CARRIER, $identity->type());
        $this->assertSame('secomm_ghn', $identity->code());
    }

    public function testCreateAcceptsKnownTypeAndCode(): void
    {
        $identity = CoverageTargetIdentity::create(CoverageTargetType::CARRIER, 'ghtk');

        $this->assertSame('ghtk', $identity->code());
    }

    public function testKeyIsStableComposite(): void
    {
        $this->assertSame('CARRIER:secomm_ghn', CoverageTargetIdentity::carrier('secomm_ghn')->key());
    }

    public function testEqualsComparesTypeAndCode(): void
    {
        $a = CoverageTargetIdentity::carrier('secomm_ghn');
        $b = CoverageTargetIdentity::create(CoverageTargetType::CARRIER, 'secomm_ghn');
        $c = CoverageTargetIdentity::carrier('ghtk');

        $this->assertTrue($a->equals($b));
        $this->assertTrue($b->equals($a));
        $this->assertFalse($a->equals($c));
        $this->assertFalse(
            CoverageTargetIdentity::carrier('secomm_ghn')
                ->equals(CoverageTargetIdentity::create(CoverageTargetType::METHOD, 'secomm_ghn'))
        );
    }

    public function testCodeIsTrimmed(): void
    {
        $this->assertSame('secomm_ghn', CoverageTargetIdentity::carrier(' secomm_ghn ')->code());
    }

    public function testUnknownTypeIsRejected(): void
    {
        $this->expectException(LocalizedException::class);
        CoverageTargetIdentity::create('WIDGET', 'some_code');
    }

    /**
     * @dataProvider malformedCodeProvider
     */
    public function testMalformedCodeIsRejected(string $code): void
    {
        $this->expectException(LocalizedException::class);
        CoverageTargetIdentity::carrier($code);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedCodeProvider(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['   '],
            'uppercase' => ['SECOMM_GHN'],
            'dash' => ['secomm-ghn'],
            'spaces inside' => ['sec omm'],
        ];
    }
}
