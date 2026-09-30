<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Cod\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Secomm\Cod\Model\CodCollectionDecision;

/**
 * TASK-DFGFZ9 phase 2 — decision VO: three constructible states, impossible states
 * unconstructible (LogicException), accessors consistent with the status.
 */
class CodCollectionDecisionTest extends TestCase
{
    public function testCollectibleCarriesAmountAndCurrency(): void
    {
        $decision = CodCollectionDecision::collectible(1250000.0, 'VND');

        $this->assertTrue($decision->isCollectible());
        $this->assertFalse($decision->isNotCod());
        $this->assertFalse($decision->isRejected());
        $this->assertSame(CodCollectionDecision::STATUS_COLLECTIBLE, $decision->getStatus());
        $this->assertSame(1250000.0, $decision->getAmount());
        $this->assertSame('VND', $decision->getCurrencyCode());
        $this->assertNull($decision->getRejectionReason());
        $this->assertNull($decision->getRejectionMessage());
    }

    public function testZeroAmountIsACollectibleCodDecision(): void
    {
        // DEC-TASKDFGFZ9-004: a COD-method order with a zero total STAYS COD — the visible
        // collection amount is exactly 0.0 (never misclassified as NOT_COD).
        $decision = CodCollectionDecision::collectible(0.0, 'VND');

        $this->assertTrue($decision->isCollectible());
        $this->assertSame(0.0, $decision->getAmount());
        $this->assertSame('VND', $decision->getCurrencyCode());
    }

    public function testNegativeAmountIsNotConstructibleAsCollectible(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('non-negative');

        CodCollectionDecision::collectible(-1.0, 'VND');
    }

    public function testNotCodHasNoAmountAndNoRejection(): void
    {
        $decision = CodCollectionDecision::notCod();

        $this->assertTrue($decision->isNotCod());
        $this->assertFalse($decision->isCollectible());
        $this->assertFalse($decision->isRejected());
        $this->assertSame(CodCollectionDecision::STATUS_NOT_COD, $decision->getStatus());
        $this->assertNull($decision->getAmount());
        $this->assertNull($decision->getCurrencyCode());
        $this->assertNull($decision->getRejectionReason());
        $this->assertNull($decision->getRejectionMessage());
    }

    public function testRejectedCarriesReasonAndMessageButNoAmount(): void
    {
        $decision = CodCollectionDecision::rejected(
            CodCollectionDecision::REASON_PARTIAL_SHIPMENT,
            'unshipped items remain'
        );

        $this->assertTrue($decision->isRejected());
        $this->assertFalse($decision->isCollectible());
        $this->assertFalse($decision->isNotCod());
        $this->assertSame(CodCollectionDecision::STATUS_REJECTED, $decision->getStatus());
        $this->assertSame(CodCollectionDecision::REASON_PARTIAL_SHIPMENT, $decision->getRejectionReason());
        $this->assertSame('unshipped items remain', $decision->getRejectionMessage());
        $this->assertNull($decision->getAmount());
        $this->assertNull($decision->getCurrencyCode());
    }

    public function testInvalidOrderAmountReasonIsWhitelisted(): void
    {
        $decision = CodCollectionDecision::rejected(
            CodCollectionDecision::REASON_INVALID_ORDER_AMOUNT,
            'negative grand total'
        );

        $this->assertSame(CodCollectionDecision::REASON_INVALID_ORDER_AMOUNT, $decision->getRejectionReason());
    }

    public function testUnknownRejectionReasonIsNotConstructible(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unknown COD collection rejection reason');

        CodCollectionDecision::rejected('made_up_reason', 'nope');
    }

    public function testEveryDeclaredReasonIsAcceptedByRejectedFactory(): void
    {
        $reasons = [
            CodCollectionDecision::REASON_CURRENCY_UNSUPPORTED,
            CodCollectionDecision::REASON_PARTIAL_SHIPMENT,
            CodCollectionDecision::REASON_COD_ALREADY_COLLECTED,
        ];

        foreach ($reasons as $reason) {
            $decision = CodCollectionDecision::rejected($reason, 'detail');
            $this->assertSame($reason, $decision->getRejectionReason());
        }
    }
}
