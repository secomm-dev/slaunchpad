<?php
/**
 * Secomm Launchpad — Snowdog Menu toggle module (TASK-SXW5RB, SLP-129)
 */

declare(strict_types=1);

namespace Launchpad\SnowdogMenu\Plugin\Snowdog\Menu\Block;

use Launchpad\SnowdogMenu\ViewModel\Config;
use Magento\Framework\Registry;
use Snowdog\Menu\Block\Menu as SnowdogMenuBlock;

/**
 * Renders every Snowdog\Menu\Block\Menu (header desktop/mobile, footer) empty
 * while the storefront navigation config selects the native Hyvä navigation,
 * so Snowdog_Menu can stay enabled (admin menu builder usable) without
 * affecting the native header.
 */
class Menu
{
    public function __construct(
        private readonly Config $config,
        private readonly Registry $registry
    ) {
    }

    public function afterToHtml(SnowdogMenuBlock $subject, string $result): string
    {
        if ($this->config->isEnabled()) {
            return $result;
        }

        return '';
    }

    /**
     * The Launchpad templates render category-current state server-side, so
     * category pages must not share one route-only Snowdog block cache entry.
     *
     * @param string[] $result
     * @return string[]
     */
    public function afterGetCacheKeyInfo(SnowdogMenuBlock $subject, array $result): array
    {
        $currentCategory = $this->registry->registry('current_category');
        $categoryId = is_object($currentCategory) && method_exists($currentCategory, 'getId')
            ? (int) $currentCategory->getId()
            : 0;

        if ($categoryId > 0) {
            $result[] = 'current_category_' . $categoryId;
        }

        return $result;
    }
}
