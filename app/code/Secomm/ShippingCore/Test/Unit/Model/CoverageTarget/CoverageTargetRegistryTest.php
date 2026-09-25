<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\CoverageTarget;

use InvalidArgumentException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\TestCase;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetIdentity;
use Secomm\ShippingCore\Api\CoverageTarget\CoverageTargetType;
use Secomm\ShippingCore\Model\CoverageTarget\CoverageTargetRegistry;

/**
 * TASK-WY6WP5 — coverage target registry: DI opt-in shape, CARRIER normalization for
 * legacy entries without a type, deterministic order, fail-fast on an unknown type typo.
 * Empty registry = valid state. Registration is metadata only — no coverage config is
 * implied (directive §3/§4).
 */
class CoverageTargetRegistryTest extends TestCase
{
    public function testEmptyRegistryIsValid(): void
    {
        $registry = new CoverageTargetRegistry([]);

        $this->assertSame([], $registry->getAll());
        $this->assertFalse($registry->has(CoverageTargetIdentity::carrier('secomm_ghn')));
    }

    public function testRegisteredTargetResolvesIdentityAndLabel(): void
    {
        $registry = new CoverageTargetRegistry([
            'secomm_ghn' => [
                'type' => CoverageTargetType::CARRIER,
                'code' => 'secomm_ghn',
                'label' => 'GHN (Giao Hàng Nhanh)',
            ],
        ]);
        $identity = CoverageTargetIdentity::carrier('secomm_ghn');

        $this->assertTrue($registry->has($identity));
        $target = $registry->get($identity);
        $this->assertSame($identity->key(), $target->getIdentity()->key());
        $this->assertSame('GHN (Giao Hàng Nhanh)', $target->getLabel());
        $this->assertSame('GHN (Giao Hàng Nhanh)', $registry->getLabel($identity));
    }

    public function testMissingTypeNormalizesToCarrier(): void
    {
        // Legacy TASK-G3K9V2 entries had no `type` key — they remain valid CARRIER targets.
        $registry = new CoverageTargetRegistry([
            'secomm_ghn' => ['code' => 'secomm_ghn', 'label' => 'GHN (Giao Hàng Nhanh)'],
        ]);

        $this->assertTrue($registry->has(CoverageTargetIdentity::carrier('secomm_ghn')));
        $this->assertSame(
            [CoverageTargetType::CARRIER],
            array_unique(array_map(
                static fn ($target) => $target->getIdentity()->type(),
                $registry->getAll()
            ))
        );
    }

    public function testScalarEntryFallsBackToKeyAndSkipsEmptyCode(): void
    {
        $registry = new CoverageTargetRegistry([
            'weird_carrier' => 'just-a-string',
            '' => ['code' => '', 'label' => 'skipped'],
        ]);

        $all = $registry->getAll();
        $this->assertCount(1, $all);
        $this->assertSame('weird_carrier', $all[0]->getIdentity()->code());
        // Label falls back to the code when the entry carries no label.
        $this->assertSame('weird_carrier', $all[0]->getLabel());
    }

    public function testUnknownTypeFailsFast(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CoverageTargetRegistry([
            'widget' => ['type' => 'WIDGET', 'code' => 'widget', 'label' => 'Widget'],
        ]);
    }

    public function testGetAllIsDeterministicallyOrderedByIdentity(): void
    {
        $registry = new CoverageTargetRegistry([
            'secomm_ghn' => ['code' => 'secomm_ghn', 'label' => 'GHN'],
            'ghtk' => ['code' => 'ghtk', 'label' => 'GHTK'],
        ]);

        $this->assertSame(
            ['CARRIER:ghtk', 'CARRIER:secomm_ghn'],
            array_map(static fn ($target) => $target->getIdentity()->key(), $registry->getAll())
        );
    }

    public function testGetByTypeFilters(): void
    {
        $registry = new CoverageTargetRegistry([
            'secomm_ghn' => ['type' => CoverageTargetType::CARRIER, 'code' => 'secomm_ghn', 'label' => 'GHN'],
        ]);

        $this->assertCount(1, $registry->getAllByType(CoverageTargetType::CARRIER));
        $this->assertSame([], $registry->getAllByType(CoverageTargetType::METHOD));
    }

    public function testGetUnregisteredThrowsNoSuchEntity(): void
    {
        $registry = new CoverageTargetRegistry([]);

        $this->expectException(NoSuchEntityException::class);
        $registry->get(CoverageTargetIdentity::carrier('ghost'));
    }

    public function testLabelForUnregisteredFallsBackToCode(): void
    {
        $registry = new CoverageTargetRegistry([]);

        $this->assertSame('ghost', $registry->getLabel(CoverageTargetIdentity::carrier('ghost')));
    }
}
