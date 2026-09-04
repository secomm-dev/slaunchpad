<?php
/**
 * Launchpad Osc — OSC address integration for the Launchpad package.
 *
 * TASK-FMAN1B / DEC-TASKFMAN1B-001: project layer owning every Mageplaza OSC
 * coupling of the VN address cascade, so Secomm_AddressDropdown stays generic
 * (default Magento checkout only).
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Launchpad_Osc', __DIR__);
