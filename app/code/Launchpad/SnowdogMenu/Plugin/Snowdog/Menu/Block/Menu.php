<?php
/**
 * Secomm Launchpad — Snowdog Menu toggle module (TASK-SXW5RB, SLP-129)
 */

declare(strict_types=1);

namespace Launchpad\SnowdogMenu\Plugin\Snowdog\Menu\Block;

use Launchpad\SnowdogMenu\ViewModel\Config;
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
        private readonly Config $config
    ) {
    }

    public function afterToHtml(SnowdogMenuBlock $subject, string $result): string
    {
        if ($this->config->isEnabled()) {
            return $result;
        }

        return '';
    }
}
