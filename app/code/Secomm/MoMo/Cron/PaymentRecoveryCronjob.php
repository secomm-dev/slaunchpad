<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\MoMo\Cron;

use Psr\Log\LoggerInterface;
use Secomm\MoMo\Service\PaymentRecovery;

/**
 * Thin cron wrapper for the MoMo lost-IPN recovery worker (MOMO-03).
 *
 * No business logic here (project rule: handlers/cron wrappers validate +
 * delegate + respond only) — the whole bounded pass lives in
 * \Secomm\MoMo\Service\PaymentRecovery. An unexpected crash must never break
 * the cron schedule: it is logged critically and the pass is simply skipped.
 */
class PaymentRecoveryCronjob
{
    /**
     * PaymentRecoveryCronjob constructor.
     *
     * @param PaymentRecovery $paymentRecovery
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PaymentRecovery $paymentRecovery,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Run one bounded recovery pass.
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $summary = $this->paymentRecovery->execute();
        } catch (\Throwable $exception) {
            $this->logger->critical(
                'MoMo payment recovery pass crashed: ' . $exception->getMessage(),
                ['exception' => $exception]
            );

            return;
        }

        if ((int)($summary['claimed'] ?? 0) > 0) {
            $this->logger->info('MoMo payment recovery pass finished.', $summary);
        }
    }
}
