<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Test\Unit\Model\Admin;

use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\Ghn\Model\Admin\GhnCreateOutcomeNotifier;
use Secomm\Ghn\Model\Shipment\GhnCreateOutcome;
use Secomm\Ghn\Model\Shipment\GhnCreateReasonLabel;

/**
 * TASK-W5BW4F layer 2 — every non-SUCCESS create outcome produces an admin error message that
 * names the status, the human reason and the retry CLI (never silent, never token-only).
 */
class GhnCreateOutcomeNotifierTest extends TestCase
{
    private ManagerInterface&MockObject $messageManager;

    private GhnCreateOutcomeNotifier $notifier;

    protected function setUp(): void
    {
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->notifier = new GhnCreateOutcomeNotifier($this->messageManager, new GhnCreateReasonLabel());
    }

    public function testUnavailableOutcomeCarriesStatusReasonAndRetryHint(): void
    {
        $this->messageManager->expects($this->once())->method('addErrorMessage')->with($this->callback(
            fn (\Magento\Framework\Phrase $message): bool => str_contains((string) $message, 'UNAVAILABLE')
                && str_contains((string) $message, 'per-package limits')
                && str_contains((string) $message, 'secomm:ghn:shipment:retry 17')
        ));

        $this->notifier->notifyFailure(
            GhnCreateOutcome::unavailable('INVALID_PARCEL', 'GHNS17'),
            17
        );
    }

    public function testCodRejectedOutcomeCarriesTheDecisionMessage(): void
    {
        $this->messageManager->expects($this->once())->method('addErrorMessage')->with($this->callback(
            fn (\Magento\Framework\Phrase $message): bool => str_contains((string) $message, 'COD_REJECTED')
                && str_contains((string) $message, 'already collected 500000')
        ));

        $this->notifier->notifyFailure(
            GhnCreateOutcome::codRejected('COD_ALREADY_COLLECTED', 'Order already collected 500000 VND.', 'GHNS17'),
            17
        );
    }

    public function testUnknownTokenRendersVerbatim(): void
    {
        $this->messageManager->expects($this->once())->method('addErrorMessage')->with($this->callback(
            fn (\Magento\Framework\Phrase $message): bool => str_contains((string) $message, 'SOME_NEW_TOKEN')
        ));

        $this->notifier->notifyFailure(
            GhnCreateOutcome::unavailable('SOME_NEW_TOKEN', 'GHNS17'),
            17
        );
    }

    public function testFailureCommentNamesTheStatusAndHumanReason(): void
    {
        $comment = $this->notifier->failureComment(
            GhnCreateOutcome::unavailable('CANONICAL_UNRESOLVED', 'GHNS17')
        );

        $this->assertSame(
            'GHN shipment create failed (UNAVAILABLE): The shipping address could not be resolved to the GHN address scheme.',
            (string) $comment
        );
    }
}
