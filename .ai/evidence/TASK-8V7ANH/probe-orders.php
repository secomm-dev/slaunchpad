<?php
/** TASK-8V7ANH — probe: find a customer-owned order for live verification. */
require '/var/www/projects/slaunchpad/app/bootstrap.php';
$om = (Magento\Framework\App\Bootstrap::create(BP, $_SERVER))->getObjectManager();

$qcEmail = 'qc-slp128b4@test.local';
$orders  = $om->create(\Magento\Sales\Model\OrderFactory::class)->create()->getCollection()
    ->addFieldToSelect(['entity_id', 'increment_id', 'customer_id', 'customer_email', 'status'])
    ->setOrder('entity_id', 'desc')->setPageSize(10);
printf("total orders probed=%d\n", $orders->getSize());
$qcCustomerId = null;
foreach ($orders as $o) {
    printf("order entity_id=%s inc=%s cust=%s email=%s status=%s\n",
        $o->getEntityId(), $o->getIncrementId(), $o->getCustomerId(), $o->getCustomerEmail(), $o->getStatus());
    if (strcasecmp((string) $o->getCustomerEmail(), $qcEmail) === 0) {
        $qcCustomerId = $o->getCustomerId();
    }
}
try {
    $c = $om->create(\Magento\Customer\Api\CustomerRepositoryInterface::class)->get($qcEmail);
    printf("qc customer exists id=%s email=%s\n", $c->getId(), $c->getEmail());
} catch (\Throwable $e) {
    printf("qc customer lookup: %s\n", $e->getMessage());
}
