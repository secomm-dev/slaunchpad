<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Cron;

use Secomm\ZaloPay\Logger\Logger;
use Secomm\ZaloPay\Service\PaymentRecovery;

/**
 * Cron entry point for the bounded lost-callback recovery worker
 * (corrective round 3, Blocker 6) — official ZaloPay guidance: query the
 * order proactively when no callback arrived within 15 minutes. Thin
 * wrapper only; every business decision lives in PaymentRecovery.
 */
class PaymentRecoveryCronjob
{
    /**
     * PaymentRecoveryCronjob constructor.
     *
     * @param PaymentRecovery $recovery
     * @param Logger $logger
     */
    public function __construct(
        private readonly PaymentRecovery $recovery,
        private readonly Logger          $logger
    ) {
    }

    /**
     * Run one bounded recovery pass (no browser session, no long locks).
     *
     * @return void
     */
    public function execute(): void
    {
        $summary = $this->recovery->execute();
        if ($summary['claimed'] > 0) {
            $this->logger->info(
                'ZaloPay payment recovery pass finished.',
                [
                    'claimed' => $summary['claimed'],
                    'finalized' => $summary['finalized'],
                    'failed' => $summary['failed'],
                    'mismatch' => $summary['mismatch'],
                    'processing' => $summary['processing'],
                    'errors' => $summary['errors'],
                ]
            );
        }
    }
}
