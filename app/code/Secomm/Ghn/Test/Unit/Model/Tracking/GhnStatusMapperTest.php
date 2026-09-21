<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Tracking;

use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Tracking\GhnStatusMapper;
use Secomm\ShippingCore\Api\Tracking\NormalizedTrackingStatus;

/**
 * TASK-GKHXY1 (GHN-E1) — the FULL explicit GHN → normalized status table (23 documented
 * provider statuses + unknown fallback). One mapper serves BOTH the webhook and the Order-Info
 * fetcher — never two diverging tables.
 */
class GhnStatusMapperTest extends TestCase
{
    private GhnStatusMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new GhnStatusMapper();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function statusProvider(): array
    {
        return [
            'ready_to_pick' => ['ready_to_pick', NormalizedTrackingStatus::CREATED],
            'picking' => ['picking', NormalizedTrackingStatus::PICKING],
            'money_collect_picking' => ['money_collect_picking', NormalizedTrackingStatus::PICKING],
            'picked' => ['picked', NormalizedTrackingStatus::PICKED_UP],
            'storing' => ['storing', NormalizedTrackingStatus::IN_TRANSIT],
            'transporting' => ['transporting', NormalizedTrackingStatus::IN_TRANSIT],
            'sorting' => ['sorting', NormalizedTrackingStatus::IN_TRANSIT],
            'delivering' => ['delivering', NormalizedTrackingStatus::OUT_FOR_DELIVERY],
            'money_collect_delivering' => ['money_collect_delivering', NormalizedTrackingStatus::OUT_FOR_DELIVERY],
            'delivered' => ['delivered', NormalizedTrackingStatus::DELIVERED],
            'delivery_fail' => ['delivery_fail', NormalizedTrackingStatus::DELIVERY_FAILED],
            'waiting_to_return' => ['waiting_to_return', NormalizedTrackingStatus::RETURNING],
            'return' => ['return', NormalizedTrackingStatus::RETURNING],
            'return_transporting' => ['return_transporting', NormalizedTrackingStatus::RETURNING],
            'return_sorting' => ['return_sorting', NormalizedTrackingStatus::RETURNING],
            'returning' => ['returning', NormalizedTrackingStatus::RETURNING],
            'return_fail' => ['return_fail', NormalizedTrackingStatus::RETURNING],
            'returned' => ['returned', NormalizedTrackingStatus::RETURNED],
            'cancel' => ['cancel', NormalizedTrackingStatus::CANCELLED],
            'damage' => ['damage', NormalizedTrackingStatus::DAMAGED],
            'lost' => ['lost', NormalizedTrackingStatus::LOST],
            'scrap' => ['scrap', NormalizedTrackingStatus::DAMAGED],
            'exception' => ['exception', NormalizedTrackingStatus::UNKNOWN],
        ];
    }

    /**
     * @dataProvider statusProvider
     */
    public function testMapsEveryDocumentedStatus(string $ghnStatus, string $expected): void
    {
        $this->assertSame($expected, $this->mapper->map($ghnStatus));
    }

    public function testMappingIsCaseAndSpaceInsensitive(): void
    {
        $this->assertSame(NormalizedTrackingStatus::DELIVERED, $this->mapper->map('  DELIVERED '));
    }

    public function testUnknownStatusIsSafeUnknown(): void
    {
        $this->assertSame(NormalizedTrackingStatus::UNKNOWN, $this->mapper->map('martian_status'));
        $this->assertSame(NormalizedTrackingStatus::UNKNOWN, $this->mapper->map(''));
        $this->assertSame(NormalizedTrackingStatus::UNKNOWN, $this->mapper->map(null));
    }

    public function testTerminalStatusesMatchTheTaxonomy(): void
    {
        foreach (['delivered', 'returned', 'cancel', 'lost', 'damage'] as $ghnStatus) {
            $this->assertTrue(
                NormalizedTrackingStatus::isTerminal($this->mapper->map($ghnStatus)),
                "$ghnStatus must normalize to a terminal status"
            );
        }
    }

    public function testDeliveryFailStaysDistinctFromLostAndDamaged(): void
    {
        // r2: delivery_fail (re-attempt possible) ≠ lost ≠ damaged (terminal write-offs).
        $this->assertSame(NormalizedTrackingStatus::DELIVERY_FAILED, $this->mapper->map('delivery_fail'));
        $this->assertNotSame($this->mapper->map('delivery_fail'), $this->mapper->map('lost'));
        $this->assertNotSame($this->mapper->map('delivery_fail'), $this->mapper->map('damage'));
        $this->assertNotSame($this->mapper->map('lost'), $this->mapper->map('damage'));
    }
}
