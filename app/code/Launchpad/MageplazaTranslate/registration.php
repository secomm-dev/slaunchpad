<?php
/**
 * Launchpad MageplazaTranslate — Vietnamese translations for Mageplaza modules.
 *
 * Keeps vendor isolation: i18n CSVs live in this Launchpad module instead of
 * app/code/Mageplaza/* so Mageplaza modules can be upgraded/overwritten freely.
 *
 * @category  Launchpad
 * @package   Launchpad_MageplazaTranslate
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Launchpad_MageplazaTranslate', __DIR__);
