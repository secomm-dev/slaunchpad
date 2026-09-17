<?php
/**
 * Secomm Launchpad — Snowdog Menu toggle module (TASK-SXW5RB, SLP-129)
 *
 * Registers the storefront navigation toggle module: Admin config that selects
 * between the native Hyvä navigation and Snowdog Menu navigation, plus the
 * frontend plugin that gates Snowdog menu output.
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Launchpad_SnowdogMenu', __DIR__);
