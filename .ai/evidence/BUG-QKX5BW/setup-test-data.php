<?php
/**
 * BUG-QKX5BW — diagnostic + test data setup (local dev only).
 * Run as secomm: sudo -u secomm php setup-test-data.php [create]
 *   (no args)  -> report only: masked customer list + enabled socials
 *   "create"   -> ensure qc-social@example.com customer + google social row (connected state)
 */
use Magento\Framework\App\Bootstrap;

require '/var/www/projects/slaunchpad/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');

$storeManager = $om->get(\Magento\Store\Model\StoreManagerInterface::class);
$websiteId = $storeManager->getWebsite()->getId();

// --- report: enabled socials (default scope) ---
$socialHelper = $om->get(\Mageplaza\SocialLogin\Helper\Social::class);
$types = $socialHelper->getSocialTypes();
foreach (array_keys($types) as $type) {
    $socialHelper->setType($type);
    echo 'social ' . str_pad($type, 12) . ' enabled=' . var_export((bool)$socialHelper->isEnabled(), true) . "\n";
}

// --- report: customers (masked emails) ---
$customerCollection = $om->create(\Magento\Customer\Model\ResourceModel\Customer\Collection::class);
echo "customers (masked):\n";
foreach ($customerCollection as $c) {
    $email = $c->getEmail();
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    $masked = substr($local, 0, 1) . '***@' . $domain;
    echo '  id=' . str_pad((string)$c->getId(), 5) . ' email=' . str_pad($masked, 26)
        . ' website=' . $c->getWebsiteId() . " store=" . $c->getStoreId() . "\n";
}

if (($argv[1] ?? '') !== 'create') {
    echo "(report only — pass 'create' to ensure test data)\n";
    exit(0);
}

// --- ensure customer qc-social@example.com ---
$email = 'qc-social@example.com';
$customerRepository = $om->get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
try {
    $customer = $customerRepository->get($email, $websiteId);
    echo "test customer exists id={$customer->getId()}\n";
} catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
    $customerFactory = $om->get(\Magento\Customer\Model\CustomerFactory::class);
    $c = $customerFactory->create();
    $c->setWebsiteId($websiteId)
        ->setStoreId((int)$storeManager->getDefaultStoreView()?->getId() ?: 1)
        ->setFirstname('QC')->setLastname('Social')
        ->setEmail($email)
        ->setPassword('QcSocial123!');
    $c->save();
    echo "test customer created id={$c->getId()}\n";
    $customer = $customerRepository->get($email, $websiteId);
}
$cid = (int)$customer->getId();

// --- ensure connected google row (idempotent) ---
$conn = $om->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
$table = $conn->getTableName('mageplaza_social_customer');
$conn->delete($table, ['customer_id = ?' => $cid, 'type = ?' => 'google']);
$conn->insert($table, [
    'social_id' => 'qc-google-test-' . $cid,
    'customer_id' => $cid,
    'is_send_password_email' => 0,
    'type' => 'google',
    'social_created_at' => date('Y-m-d H:i:s'),
]);
$count = (int)$conn->fetchOne(
    "SELECT COUNT(*) FROM {$table} WHERE customer_id = ? AND type = 'google'",
    [$cid]
);
echo "google connected rows for customer {$cid}: {$count}\n";
