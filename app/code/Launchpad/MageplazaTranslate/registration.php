<?php
/**
 * Secomm MageplazaTranslate — Vietnamese translations for Mageplaza modules.
 *
 * Keeps vendor isolation: i18n CSVs live in this Secomm module instead of
 * app/code/Mageplaza/* so Mageplaza modules can be upgraded/overwritten freely.
 *
 * @category  Secomm
 * @package   Secomm_MageplazaTranslate
 */
use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Secomm_MageplazaTranslate', __DIR__);
