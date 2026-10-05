<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CurrencyPrecision\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Single source of truth for display decimal precision (ticket "Format Price
 * Product"). null = Auto — keep the locale/currency default, inject nothing.
 *
 * Hot path: resolve() runs once per rendered price (thousands per PLP), so the
 * parsed config map is memoized per store for the request lifetime; plugins
 * receive this class as a DI proxy so construction stays lazy.
 */
class PrecisionResolver
{
    public const XML_PATH_DEFAULT = 'currency/display_precision/default_precision';
    public const XML_PATH_PAIRS = 'currency/display_precision/pairs';

    private const PAIR_PATTERN = '/^([A-Z]{3})\s*=\s*([0-4])$/i';

    /**
     * @var array<int, array<string, int>>
     */
    private array $mapCache = [];

    /**
     * @var array<int, int|null>
     */
    private array $defaultCache = [];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * Precision for a currency in the current store scope, or null for Auto.
     */
    public function resolve(string $currencyCode): ?int
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $map = $this->getMap($storeId);
        $code = strtoupper(trim($currencyCode));
        if ($code !== '' && array_key_exists($code, $map)) {
            return $map[$code];
        }

        return $this->resolveDefault($storeId);
    }

    /**
     * Precision for the store's current display currency, or null for Auto.
     */
    public function resolveCurrent(): ?int
    {
        return $this->resolve((string)$this->storeManager->getStore()->getCurrentCurrencyCode());
    }

    /**
     * @return array<string, int>
     */
    private function getMap(int $storeId): array
    {
        if (isset($this->mapCache[$storeId])) {
            return $this->mapCache[$storeId];
        }

        $raw = (string)$this->scopeConfig->getValue(
            self::XML_PATH_PAIRS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $map = [];
        foreach (explode(',', $raw) as $pair) {
            if (preg_match(self::PAIR_PATTERN, trim($pair), $matches) === 1) {
                $map[strtoupper($matches[1])] = (int)$matches[2];
            }
        }

        return $this->mapCache[$storeId] = $map;
    }

    private function resolveDefault(int $storeId): ?int
    {
        if (array_key_exists($storeId, $this->defaultCache)) {
            return $this->defaultCache[$storeId];
        }

        $default = (string)$this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        // 'auto' (or empty) = keep native locale/currency behavior.
        return $this->defaultCache[$storeId] = is_numeric($default) ? (int)$default : null;
    }
}
