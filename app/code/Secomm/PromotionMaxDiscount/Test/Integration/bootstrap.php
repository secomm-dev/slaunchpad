<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

/**
 * TASK-4HYX6Y integration bootstrap — Option 2 (engine-level, TL decision
 * 2026-08-21): full Magento app against the DEV database via PHPUnit.
 *
 * The standard TestFramework (dev/tests/integration, isolated test DB) was
 * never configured in this project; per the approved fallback these tests run
 * engine-level. Consequences handled by the test classes:
 *  - fixtures use unique SKUs per test and are removed in tearDown/finally
 *  - no reliance on auto-increment ids or leftover state (repeat-safe ×3)
 *  - run command: vendor/bin/phpunit --no-configuration \
 *      --bootstrap app/code/Secomm/PromotionMaxDiscount/Test/Integration/bootstrap.php \
 *      app/code/Secomm/PromotionMaxDiscount/Test/Integration/
 */

require '/var/www/html/slaunchpad/app/bootstrap.php';

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
/** @var \Magento\Framework\ObjectManagerInterface $GLOBALS['__integrationOm'] */
$GLOBALS['__integrationOm'] = $bootstrap->getObjectManager();
$GLOBALS['__integrationOm']->get(\Magento\Framework\App\State::class)->setAreaCode('frontend');

// Test namespace is not a registered Magento component — map it like the unit
// bootstrap does for the module tree.
$loader = require '/var/www/html/slaunchpad/vendor/autoload.php';
$loader->addPsr4('Secomm\\', '/var/www/html/slaunchpad/app/code/Secomm/');
