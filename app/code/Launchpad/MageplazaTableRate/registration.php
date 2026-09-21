<?php
/**
 * TASK-NQT782 (LT-BRIDGE-1) — Launchpad-specific composition around Mageplaza TableRate.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Launchpad_MageplazaTableRate',
    __DIR__
);
