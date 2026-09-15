<?php
/**
 * Secomm Launchpad — Quick View module (TASK-Z3DAH5, SLP-157)
 *
 * Registers the Quick View feature module: storefront config (enable/disable)
 * + view model consumed by the Secomm/launchpad theme.
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Launchpad_QuickView', __DIR__);
