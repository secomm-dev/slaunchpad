<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\VietQr\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

/**
 * IP-based rate limiter for the public VietQR payment pages
 * (TASK-N35E28 / SPEC-FEAT-ZKD4VA §3 AC-020).
 *
 * Mitigates brute-force attempts against order IDs / guest protect codes
 * on the VietQR view/submit endpoints.
 */
class RateLimiter
{
    private const CACHE_PREFIX = 'secomm_vietqr_rl_';
    private const MAX_ATTEMPTS = 10;
    private const WINDOW_SECONDS = 60;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly RemoteAddress $remoteAddress
    ) {
    }

    /**
     * Whether the current client IP is allowed to proceed.
     *
     * @return bool
     */
    public function isAllowed(): bool
    {
        $key = self::CACHE_PREFIX . $this->getClientIp();
        $attempts = (int)$this->cache->load($key);

        if ($attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        $this->cache->save((string)($attempts + 1), $key, [], self::WINDOW_SECONDS);

        return true;
    }

    /**
     * @return string
     */
    private function getClientIp(): string
    {
        return (string)$this->remoteAddress->getRemoteAddress() ?: '0.0.0.0';
    }
}
