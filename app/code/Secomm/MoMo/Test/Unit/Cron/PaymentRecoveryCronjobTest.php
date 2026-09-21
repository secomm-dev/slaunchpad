<?php
/**
 * Unit test for the thin MoMo recovery cron wrapper (MOMO-03).
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Test\Unit\Cron;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Secomm\MoMo\Cron\PaymentRecoveryCronjob;
use Secomm\MoMo\Service\PaymentRecovery;

/**
 * Verifies the cron wrapper: delegates one pass, logs the summary when the
 * pass claimed work, and never breaks the schedule on a crash.
 */
class PaymentRecoveryCronjobTest extends TestCase
{
    private PaymentRecovery&MockObject $paymentRecovery;

    private LoggerInterface&MockObject $logger;

    private PaymentRecoveryCronjob $cronjob;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->paymentRecovery = $this->createMock(PaymentRecovery::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cronjob = new PaymentRecoveryCronjob($this->paymentRecovery, $this->logger);
    }

    /**
     * A pass that claimed work logs its summary at info level.
     *
     * @return void
     */
    public function testDelegatesAndLogsSummaryWhenWorkClaimed(): void
    {
        $summary = ['claimed' => 2, 'finalized' => 1, 'errors' => 0];
        $this->paymentRecovery->expects($this->once())->method('execute')->willReturn($summary);
        $this->logger->expects($this->once())->method('info')
            ->with('MoMo payment recovery pass finished.', $summary);
        $this->logger->expects($this->never())->method('critical');

        $this->cronjob->execute();
    }

    /**
     * An idle pass (nothing claimed) stays silent — no log noise.
     *
     * @return void
     */
    public function testIdlePassLogsNothing(): void
    {
        $this->paymentRecovery->method('execute')->willReturn(['claimed' => 0]);
        $this->logger->expects($this->never())->method('info');
        $this->logger->expects($this->never())->method('critical');

        $this->cronjob->execute();
    }

    /**
     * A crash inside the worker is logged critically and swallowed — the
     * cron schedule itself must never break.
     *
     * @return void
     */
    public function testCrashIsLoggedCriticalAndSwallowed(): void
    {
        $this->paymentRecovery->method('execute')->willThrowException(
            new \RuntimeException('DB gone')
        );
        $this->logger->expects($this->once())->method('critical')
            ->with($this->stringContains('DB gone'), $this->anything());
        $this->logger->expects($this->never())->method('info');

        $this->cronjob->execute();
    }
}
