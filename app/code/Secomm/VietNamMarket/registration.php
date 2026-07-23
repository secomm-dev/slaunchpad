<?php
/**
 * Secomm_VietNamMarket — Vietnam market setup (locale registration, etc.)
 * Registers the `en_VN` (English - Vietnam) locale so it is selectable per store-view,
 * enabling VN-specific label translations (e.g. address "City" → "Ward/Commune")
 * without polluting the default `en_US` locale.
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Secomm_VietNamMarket', __DIR__);
