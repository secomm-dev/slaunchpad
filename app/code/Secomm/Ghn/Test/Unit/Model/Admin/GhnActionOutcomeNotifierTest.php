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
use Secomm\Ghn\Model\Admin\GhnActionOutcomeNotifier;
use Secomm\Ghn\Model\Shipment\GhnActionOutcome;

/**
 * TASK-PWHG0V pre-review fix pack — the shared admin outcome→message map (brief §19 semantics
 * are preserved verbatim for both Cancel and Return). Presentation only.
 */
class GhnActionOutcomeNotifierTest extends TestCase
{
    private ManagerInterface&MockObject $messages;

    private GhnActionOutcomeNotifier $notifier;

    protected function setUp(): void
    {
        $this->messages = $this->createMock(ManagerInterface::class);
        $this->notifier = new GhnActionOutcomeNotifier($this->messages);
    }

    private function assertPhrase(callable $trigger, string $method, string $needle): void
    {
        $captured = null;
        $this->messages->expects($this->once())->method($method)->willReturnCallback(
            function (\Magento\Framework\Phrase $phrase) use (&$captured) {
                $captured = (string) $phrase;

                return $this->messages;
            }
        );
        $trigger();
        $this->assertNotNull($captured);
        $this->assertStringContainsString($needle, $captured);
    }

    public function testCancelSuccessMessage(): void
    {
        $this->assertPhrase(
            fn () => $this->notifier->notify(GhnActionOutcome::success(GhnActionOutcome::ACTION_CANCEL, 'L8NEW')),
            'addSuccessMessage',
            'cancellation accepted'
        );
    }

    public function testReturnSuccessMessage(): void
    {
        $this->assertPhrase(
            fn () => $this->notifier->notify(GhnActionOutcome::success(GhnActionOutcome::ACTION_RETURN, 'L8NEW')),
            'addSuccessMessage',
            'return request accepted'
        );
    }

    public function testBusinessRejectedCarriesSafeProviderReason(): void
    {
        $outcome = GhnActionOutcome::businessRejected(
            GhnActionOutcome::ACTION_CANCEL,
            'L8NEW',
            'PROVIDER_REJECTED',
            'Đơn đã giao'
        );
        $this->assertPhrase(
            fn () => $this->notifier->notify($outcome),
            'addErrorMessage',
            'Đơn đã giao'
        );
    }

    public function testTechnicalFailureShowsNoRetryMessage(): void
    {
        $outcome = GhnActionOutcome::technicalFailure(GhnActionOutcome::ACTION_RETURN, 'L8NEW', 'TECHNICAL_ERROR', '429');
        $this->assertPhrase(
            fn () => $this->notifier->notify($outcome),
            'addErrorMessage',
            'temporarily unavailable'
        );
    }

    public function testUnknownResultShowsReconcileFirstNotice(): void
    {
        $outcome = GhnActionOutcome::unknownResult(GhnActionOutcome::ACTION_CANCEL, 'L8NEW', 'timeout');
        $this->assertPhrase(
            fn () => $this->notifier->notify($outcome),
            'addNoticeMessage',
            'refresh/reconcile'
        );
    }
}
