<?php
/**
 * TASK-8V7ANH — server-side QC session for live order-view verification.
 * Logs a customer in WITHOUT password (QC only, local env — pattern per
 * LESSONS_LEARNED LL / memory: login không cần captcha, tạo session as secomm).
 * Prints the PHPSESSID to reuse with curl `-b "PHPSESSID=<id>"`.
 * Run as secomm: php create-session.php <customerId>
 */
require '/var/www/projects/slaunchpad/app/bootstrap.php';

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;

$om = (Bootstrap::create(BP, $_SERVER))->getObjectManager();
$om->get(State::class)->setAreaCode('frontend');

$customerId = (int) ($argv[1] ?? 3);
$customer   = $om->create(\Magento\Customer\Model\CustomerFactory::class)->create()->load($customerId);
if (!$customer->getId()) {
    fwrite(STDERR, "customer {$customerId} not found\n");
    exit(1);
}

$session = $om->get(\Magento\Customer\Model\Session::class);
$session->setCustomer($customer);
$session->regenerateId();
$session->writeClose(); // persist session file before CLI exits

echo $session->getSessionId(), PHP_EOL;
