<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model;

use Magento\Framework\App\CacheInterface;
use Secomm\Ghtk\Model\Config\GhtkConfig;

/**
 * Short-TTL cache for composed GHTK rate amounts (AC-012). The key MUST include every
 * parameter that affects the rate (pickup + destination identity, weight, declared
 * value, transport) — never the token or sensitive data. TTL from config.
 */
class RateCache
{
    public const TAG = 'secomm_ghtk_rate';
    private const PREFIX = 'ghtk_rate_';

    public function __construct(
        private CacheInterface $cache,
        private GhtkConfig $config
    ) {
    }

    public function load(string $key): ?float
    {
        $raw = $this->cache->load(self::PREFIX . $key);
        if ($raw === false) {
            return null;
        }
        $value = filter_var($raw, FILTER_VALIDATE_FLOAT);

        return $value === false ? null : (float) $value;
    }

    public function save(string $key, float $amount): void
    {
        $this->cache->save(
            (string) $amount,
            self::PREFIX . $key,
            [self::TAG],
            $this->config->getCacheTtl()
        );
    }

    public function clean(): void
    {
        $this->cache->clean([self::TAG]);
    }
}
