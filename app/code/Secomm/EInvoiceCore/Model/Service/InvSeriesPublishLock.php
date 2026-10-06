<?php

declare(strict_types=1);

namespace Secomm\EInvoiceCore\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;

/**
 * Serializes publish requests per MeInvoice InvSeries (§1.6).
 */
class InvSeriesPublishLock
{
    private const LOCK_PREFIX = 'secomm_einvoice_inv_series_';
    private const LOCK_TIMEOUT = 120;

    public function __construct(
        private readonly LockManagerInterface $lockManager
    ) {
    }

    /**
     * @template T
     * @param string $invSeries
     * @param callable(): T $callback
     * @return T
     * @throws LocalizedException
     */
    public function execute(string $invSeries, callable $callback): mixed
    {
        $series = trim($invSeries);
        if ($series === '') {
            return $callback();
        }

        $lockName = self::LOCK_PREFIX . preg_replace('/[^a-zA-Z0-9_-]/', '_', $series);
        if (!$this->lockManager->lock($lockName, self::LOCK_TIMEOUT)) {
            throw new LocalizedException(
                __('Another invoice publish is in progress for series %1. Please try again.', $series)
            );
        }

        try {
            return $callback();
        } finally {
            $this->lockManager->unlock($lockName);
        }
    }
}
