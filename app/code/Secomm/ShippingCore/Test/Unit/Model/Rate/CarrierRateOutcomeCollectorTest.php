<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — collector execution isolation contract tests.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Test\Unit\Model\Rate;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeCollectorInterface;
use Secomm\ShippingCore\Api\Rate\CarrierRateOutcomeInterface;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcome;
use Secomm\ShippingCore\Model\Rate\CarrierRateOutcomeCollector;

class CarrierRateOutcomeCollectorTest extends TestCase
{
    private CarrierRateOutcomeCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new CarrierRateOutcomeCollector(new NullLogger());
    }

    private function record(string $carrier, string $method, string $status): void
    {
        $outcome = $status === CarrierRateOutcomeInterface::STATUS_SUCCESS
            ? CarrierRateOutcome::success(new \Secomm\ShippingCore\Model\Rate\CarrierRate(32000.0))
            : CarrierRateOutcome::unavailable('X');
        $this->collector->record($carrier, $method, $outcome);
    }

    public function testSuccessRecorded(): void
    {
        $this->collector->beginCollection();
        $this->record('secomm_ghn', 'secomm_ghn', 'SUCCESS');

        $outcomes = $this->collector->getOutcomes();
        $this->assertTrue($outcomes['secomm_ghn']['secomm_ghn']->isSuccessful());
    }

    public function testTechnicalFailureRecorded(): void
    {
        $this->collector->beginCollection();
        $this->collector->record('secomm_ghn', 'secomm_ghn', CarrierRateOutcome::technicalFailure('TECHNICAL_ERROR'));

        $this->assertSame(
            CarrierRateOutcomeInterface::STATUS_TECHNICAL_FAILURE,
            $this->collector->getOutcomes()['secomm_ghn']['secomm_ghn']->getStatus()
        );
    }

    public function testPairIdentityAndMultipleCarriers(): void
    {
        $this->collector->beginCollection();
        $this->record('secomm_ghn', 'secomm_ghn', 'SUCCESS');
        $this->record('ghtk', 'ghtk_standard', 'SUCCESS');

        $outcomes = $this->collector->getOutcomes();
        $this->assertCount(2, $outcomes);
        $this->assertArrayHasKey('ghtk_standard', $outcomes['ghtk']);
    }

    public function testLastWinsPerPair(): void
    {
        $this->collector->beginCollection();
        $this->collector->record('c', 'm', CarrierRateOutcome::technicalFailure('TECHNICAL_ERROR'));
        $this->collector->record('c', 'm', CarrierRateOutcome::unavailable('SERVICE_UNAVAILABLE'));

        $this->assertSame(
            CarrierRateOutcomeInterface::STATUS_UNAVAILABLE,
            $this->collector->getOutcomes()['c']['m']->getStatus()
        );
    }

    /** TASK-5XQXZK QC §1B — a later SUCCESS must always win (never a stuck failure). */
    public function testTechnicalFailureOverwrittenBySuccess(): void
    {
        $this->collector->beginCollection();
        $this->collector->record('c', 'm', CarrierRateOutcome::technicalFailure('TECHNICAL_ERROR'));
        $this->collector->record('c', 'm', CarrierRateOutcome::success(new \Secomm\ShippingCore\Model\Rate\CarrierRate(32000.0)));

        $this->assertTrue($this->collector->getOutcomes()['c']['m']->isSuccessful());
    }

    /** TASK-5XQXZK QC §1B — UNAVAILABLE then SUCCESS: final = SUCCESS. */
    public function testUnavailableOverwrittenBySuccess(): void
    {
        $this->collector->beginCollection();
        $this->collector->record('c', 'm', CarrierRateOutcome::unavailable('SERVICE_UNAVAILABLE'));
        $this->collector->record('c', 'm', CarrierRateOutcome::success(new \Secomm\ShippingCore\Model\Rate\CarrierRate(32000.0)));

        $this->assertTrue($this->collector->getOutcomes()['c']['m']->isSuccessful());
    }

    /**
     * TASK-5XQXZK QC §1B — contract is last-wins. A SUCCESS followed by a diagnostic
     * non-success is NOT reachable in the current carrier flows (each carrier records exactly
     * once per collection execution — every gate path terminal-returns), so no downgrade
     * guard is built: if a future carrier ever re-enters after reporting SUCCESS, that is a
     * carrier defect and last-wins surfaces it faithfully.
     */
    public function testLaterDiagnosticWinsByContract(): void
    {
        $this->collector->beginCollection();
        $this->collector->record('c', 'm', CarrierRateOutcome::success(new \Secomm\ShippingCore\Model\Rate\CarrierRate(32000.0)));
        $this->collector->record('c', 'm', CarrierRateOutcome::unavailable('SERVICE_UNAVAILABLE'));

        $this->assertSame(
            CarrierRateOutcomeInterface::STATUS_UNAVAILABLE,
            $this->collector->getOutcomes()['c']['m']->getStatus()
        );
    }

    public function testSequentialCollectionsDoNotLeak(): void
    {
        $this->collector->beginCollection();
        $this->record('secomm_ghn', 'secomm_ghn', 'SUCCESS');
        $this->collector->endCollection();

        $this->collector->beginCollection();
        $this->record('ghtk', 'ghtk_standard', 'SUCCESS');

        $outcomes = $this->collector->getOutcomes();
        $this->assertArrayNotHasKey('secomm_ghn', $outcomes);
        $this->assertArrayHasKey('ghtk', $outcomes);
    }

    public function testOrphanRecordIsDroppedSafely(): void
    {
        // No beginCollection() — must not throw, must not persist.
        $this->record('secomm_ghn', 'secomm_ghn', 'SUCCESS');
        $this->collector->beginCollection();
        $this->assertSame([], $this->collector->getOutcomes());
    }

    public function testEmptyIdentityIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->collector->record('', 'm', CarrierRateOutcome::unavailable(null));
    }

    public function testGetOutcomesEmptyAfterEnd(): void
    {
        $this->collector->beginCollection();
        $this->record('c', 'm', 'SUCCESS');
        $this->collector->endCollection();

        $this->assertSame([], $this->collector->getOutcomes());
    }

    public function testInterfaceStable(): void
    {
        $this->assertInstanceOf(CarrierRateOutcomeCollectorInterface::class, $this->collector);
    }
}
