<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Shipment;

use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Shipment\GhnActionOutcome;

/**
 * TASK-4ATBC4 (GHN-E2) — normalized lifecycle-action outcome: factory semantics + success flag.
 */
class GhnActionOutcomeTest extends TestCase
{
    public function testSuccessFactory(): void
    {
        $outcome = GhnActionOutcome::success('cancel', 'L8TKYG');

        $this->assertTrue($outcome->isSuccessful());
        $this->assertSame(GhnActionOutcome::STATUS_SUCCESS, $outcome->getStatus());
        $this->assertSame('cancel', $outcome->getAction());
        $this->assertSame('L8TKYG', $outcome->getProviderOrderCode());
        $this->assertNull($outcome->getReasonCode());
    }

    public function testBusinessRejectedFactory(): void
    {
        $outcome = GhnActionOutcome::businessRejected('return', 'L8TKYG', 'PROVIDER_REJECTED', 'invalid state');

        $this->assertFalse($outcome->isSuccessful());
        $this->assertSame(GhnActionOutcome::STATUS_BUSINESS_REJECTED, $outcome->getStatus());
        $this->assertSame('PROVIDER_REJECTED', $outcome->getReasonCode());
        $this->assertSame('invalid state', $outcome->getMessage());
    }

    public function testTechnicalFailureFactory(): void
    {
        $outcome = GhnActionOutcome::technicalFailure('cancel', 'L8TKYG', 'TECHNICAL_ERROR', 'timed out');

        $this->assertSame(GhnActionOutcome::STATUS_TECHNICAL_FAILURE, $outcome->getStatus());
        $this->assertSame('TECHNICAL_ERROR', $outcome->getReasonCode());
    }

    public function testUnknownResultFactory(): void
    {
        $outcome = GhnActionOutcome::unknownResult('cancel', 'L8TKYG', 'response lost');

        $this->assertSame(GhnActionOutcome::STATUS_UNKNOWN_RESULT, $outcome->getStatus());
        $this->assertSame('UNKNOWN', $outcome->getReasonCode());
        $this->assertSame('response lost', $outcome->getMessage());
    }
}
