<?php
/**
 * Secomm Launchpad — Snowdog Menu toggle module (TASK-SXW5RB, SLP-129)
 */

declare(strict_types=1);

namespace Launchpad\SnowdogMenu\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Storefront config that selects between the native Hyvä navigation and
 * Snowdog Menu navigation in the Secomm/launchpad theme.
 */
class Config implements ArgumentInterface
{
    public const CONFIG_PATH_ENABLED = 'snowdog_navigation/general/enabled';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?string $storeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeCode
        );
    }
}
