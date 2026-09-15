<?php
/**
 * Secomm Launchpad — Quick View module (TASK-Z3DAH5, SLP-157)
 */

declare(strict_types=1);

namespace Launchpad\QuickView\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Storefront config for the Quick View feature (theme Secomm/launchpad).
 */
class Config implements ArgumentInterface
{
    public const CONFIG_PATH_ENABLED = 'hyva_theme_quickview/general/enabled';
    public const CONFIG_PATH_MONSOON_OPEN_CART = 'checkout/options/ajax_cart_open_after_add_to_cart';

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

    /**
     * Mirrors Monsoon_HyvaAjaxAddToCart behaviour: when the storefront opens the
     * mini-cart drawer after add-to-cart, the Quick View modal does the same
     * (closes itself and hands over to the drawer) so ATC UX stays consistent.
     */
    public function isOpenCartAfterAddToCart(?string $storeCode = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_MONSOON_OPEN_CART,
            ScopeInterface::SCOPE_STORE,
            $storeCode
        );
    }
}
