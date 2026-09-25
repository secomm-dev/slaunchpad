<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Carrier;

use PHPUnit\Framework\TestCase;

/**
 * TASK-SEC-D r4 — transport contract tripwire: every GHN rate-collection record site must go
 * through `recordDecision()` (atomic outcome + eligibility transport). A future change that
 * re-introduces a legacy `recordOutcome(...)` CALL inside the carrier fails here.
 *
 * This is a source-contract test by design (call-graph was traced in the audit evidence);
 * runtime coverage of the transported decisions lives in GhnZoneExecutionTest/GhnTest.
 */
class GhnTransportContractTest extends TestCase
{
    private const CARRIER_FILE = __DIR__ . '/../../../../Model/Carrier/Ghn.php';

    public function testNoLegacyOutcomeRecordCallSitesRemainInTheCarrier(): void
    {
        $code = (string) file_get_contents(self::CARRIER_FILE);

        // The helper definition itself is allowed (kept for BC); CALL SITES are not.
        preg_match_all('/\$this->recordOutcome\(/', $code, $calls);
        $this->assertSame(
            [],
            $calls[0],
            'GHN carrier must not call legacy recordOutcome() — use recordDecision() with an explicit transport eligibility'
        );

        // And the carrier must actually use the transported decision recorder.
        preg_match_all('/\$this->recordDecision\(/', $code, $decisions);
        $this->assertGreaterThanOrEqual(
            4,
            count($decisions[0]),
            'Every GHN rate path (VN gate, VND gate, skip/blocked, realtime) records a transported decision'
        );
    }

    public function testAllRecordedDecisionsCarryExplicitEligibility(): void
    {
        $code = (string) file_get_contents(self::CARRIER_FILE);

        // Every recordDecision call must pass a FallbackEligibility argument (explicit NONE
        // or the decision's transported eligibility) — never a null/absent transport.
        preg_match_all('/recordDecision\(([^;]*?)\);/s', $code, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $i => $args) {
            $this->assertTrue(
                str_contains($args, 'FallbackEligibility') || str_contains($args, 'getFallbackEligibility'),
                "recordDecision call #{$i} must carry an explicit transport eligibility argument"
            );
        }
    }
}
