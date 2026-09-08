<?php
declare(strict_types=1);

/**
 * Launchpad MageplazaRewrite — single home for Mageplaza-related fixes of the
 * Launchpad package, so future Mageplaza fixes land in one place instead of
 * per-bug modules. Fixes are requirejs mixins / plugins — never edits to
 * app/code/Mageplaza/* in place (vendor isolation).
 *
 * First fix: BUG-SRF024 (SLP-147) — admin datepicker position mixin
 * (mage.calendar / mage.dateRange). Root cause is core mage/calendar
 * (_overwriteFindPos, magento/magento2#40083) but it surfaces on the
 * Mageplaza → Delivery Time admin config page.
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Launchpad_MageplazaRewrite', __DIR__);
