<?php
/**
 * Copyright © Secomm. All rights reserved.
 *
 * Homepage custom widgets (flash sale slider, journal blog slider) and
 * newsletter block for the LAUNCHPAD CORE homepage (TASK-0NNZCW, SLP-213).
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Launchpad_Homepage',
    __DIR__
);
