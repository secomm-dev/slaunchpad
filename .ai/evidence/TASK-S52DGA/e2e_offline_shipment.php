<?php
/**
 * TASK-S52DGA — code-level E2E for the generic Offline Shipment P1 flow (ADMIN_SMOKE is a
 * human/browser step; this script exercises the REAL save mechanics end to end on the local
 * dev DB, the same way the core Save controller does: ShipmentDocumentFactory → register()
 * → DB\Transaction, with the offline intent present in the request).
 *
 * Run: php .ai/evidence/TASK-S52DGA/e2e_offline_shipment.php
 */
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\ShipmentDocumentFactory;
use Secomm\ShippingCore\Api\Shipment\FulfillmentMode;
use Secomm\ShippingCore\Model\Shipment\FulfillmentMetadataPersister;
use Secomm\ShippingCore\Model\Shipment\FulfillmentModeResolver;

require __DIR__ . '/../../../app/bootstrap.php';
$params = $_SERVER;
$params[\Magento\Store\Model\StoreManager::PARAM_RUN_CODE] = 'admin';
$params[\Magento\Store\Model\Store::ADMIN_CODE] = 'admin';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $params);
$objectManager = $bootstrap->getObjectManager();

/** @var State $state */
$state = $objectManager->get(State::class);
$state->setAreaCode(Area::AREA_ADMINHTML);

/** @var \Magento\Framework\App\Request\Http $request */
$request = $objectManager->get(\Magento\Framework\App\Request\Http::class);
$request->setMethod('POST');

$failures = [];
$check = function (string $name, bool $ok, string $detail = '') use (&$failures): void {
    printf("%s %s%s\n", $ok ? 'PASS' : 'FAIL', $name, $detail !== '' ? " — {$detail}" : '');
    if (!$ok) {
        $failures[] = $name;
    }
};

// ---- Pick a GHN order that can still ship -----------------------------------------------
/** @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory */
$orderCollectionFactory = $objectManager->get(\Magento\Sales\Model\ResourceModel\Order\CollectionFactory::class);
$order = null;
foreach ($orderCollectionFactory->create()
    ->addFieldToFilter('shipping_method', ['like' => 'secomm_ghn_%']) as $candidate) {
    if ($candidate->canShip()) {
        $order = $candidate;
        break;
    }
}
$order = $order ?? new Order();
/** @var Order $order */
if (!$order->getId() || !$order->canShip()) {
    // Fall back to any GHN order (canShip check repeated below for the report).
    $order = $orderCollectionFactory->create()
        ->addFieldToFilter('shipping_method', ['like' => 'secomm_ghn_%'])
        ->setPageSize(1)
        ->getFirstItem();
}
$check('GHN order available', $order->getId() > 0, 'order_id=' . $order->getId());
if (!$order->getId()) {
    exit(1);
}
$check('order canShip', (bool) $order->canShip());
if (!$order->canShip()) {
    echo "No shippable GHN order on this dev DB — create one (place an order with the GHN method) and rerun.\n";
    exit(1);
}

$orderId = (int) $order->getId();

// ---- CASE A: offline save end to end (no GHN API, no anchor, no COD, no track) ----------
$constraintViolating = [ // 300 cm sides — would fail the GHN create hard limit
    ['weight' => 48.6, 'length' => 300, 'width' => 300, 'height' => 300],
];
$request->setParams([
    'order_id' => $orderId,
    'shipment' => [
        'items' => [],
        'physical_packages' => $constraintViolating,
        'fulfillment_mode' => FulfillmentMode::OFFLINE,
        'offline_reason_code' => 'INVALID_PARCEL',
        'offline_reason_message' => 'GHN create: package #1 length is 300 cm — above the 200 cm per-side limit.',
        'offline_note' => 'E2E offline record — booked manually.',
    ],
]);

// Native shipment build (identical shape to the core Save controller path).
/** @var ShipmentDocumentFactory $documentFactory */
$documentFactory = $objectManager->get(ShipmentDocumentFactory::class);
$shipment = $documentFactory->create($order, [], []); // empty items map = ship full remaining qty
$shipment->register();

// The pre-save event fires INSIDE the save, exactly as in production.
/** @var \Magento\Framework\DB\Transaction $transaction */
$transaction = $objectManager->get(\Magento\Framework\DB\Transaction::class);
$transaction->addObject($shipment)->addObject($shipment->getOrder())->save();

$shipmentId = (int) $shipment->getEntityId();
$check('A1 Magento shipment created', $shipmentId > 0, "shipment_id={$shipmentId}");

/** @var \Magento\Sales\Model\ResourceModel\Order\Shipment $shipmentResource */
$shipmentResource = $objectManager->get(\Magento\Sales\Model\ResourceModel\Order\Shipment::class);
$rawPackages = (string) $shipmentResource->getConnection()->fetchOne(
    $shipmentResource->getConnection()->select()
        ->from($shipmentResource->getMainTable(), ['packages'])
        ->where('entity_id = ?', $shipmentId)
);
$packagesJson = json_decode($rawPackages ?: '[]', true) ?: [];
$check('A2 fulfillment marker persisted', isset($packagesJson[FulfillmentMetadataPersister::PACKAGES_KEY][FulfillmentMetadataPersister::MODE])
    && $packagesJson[FulfillmentMetadataPersister::PACKAGES_KEY][FulfillmentMetadataPersister::MODE] === FulfillmentMode::OFFLINE);
$check('A2b physical facts persisted', isset($packagesJson['secomm_physical'])
    && $packagesJson['secomm_physical'] === [[48600, 300, 300, 300]],
    json_encode($packagesJson['secomm_physical'] ?? null));

/** @var \Magento\Framework\App\ResourceConnection $resources */
$connection = $objectManager->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
$anchorCount = (int) $connection->fetchOne(
    $connection->select()->from('secomm_ghn_shipment', ['COUNT(*)'])->where('magento_shipment_id = ?', $shipmentId)
);
$check('A3 no GHN provider anchor', $anchorCount === 0, "rows={$anchorCount}");
$codCount = (int) $connection->fetchOne(
    $connection->select()->from('secomm_cod_collection', ['COUNT(*)'])->where('magento_order_id = ?', $orderId)
);
$check('A4 no COD ledger claim', $codCount === 0, "rows={$codCount} (note: pre-existing rows for this order count)");
$trackCount = (int) $connection->fetchOne(
    $connection->select()->from('sales_shipment_track', ['COUNT(*)'])->where('parent_id = ?', $shipmentId)
);
$check('A5 no tracking row', $trackCount === 0, "rows={$trackCount}");
$commentText = (string) $connection->fetchOne(
    $connection->select()->from('sales_shipment_comment', ['comment'])
        ->where('parent_id = ?', $shipmentId)
        ->order('entity_id DESC')->limit(1)
);
$check('A6 offline history comment', str_contains($commentText, 'Offline shipment created'), substr($commentText, 0, 90));

// ---- CASE B: re-save (comment) of the offline shipment must NOT touch GHN ----------------
$request->setParams([]); // no intent — a natural comment re-save
/** @var \Magento\Sales\Api\ShipmentRepositoryInterface $shipmentRepository */
$shipmentRepository = $objectManager->get(\Magento\Sales\Api\ShipmentRepositoryInterface::class);
$reloaded = $shipmentRepository->get($shipmentId);
$reloaded->addComment(__('B2B smoke comment (TASK-S52DGA re-save check).'));
$reloaded->save();

$anchorCountB = (int) $connection->fetchOne(
    $connection->select()->from('secomm_ghn_shipment', ['COUNT(*)'])->where('magento_shipment_id = ?', $shipmentId)
);
$check('B1 re-save creates no GHN anchor', $anchorCountB === 0, "rows={$anchorCountB}");

// ---- CASE C: the generic read seam sees OFFLINE -----------------------------------------
/** @var FulfillmentModeResolver $resolver */
$resolver = $objectManager->get(FulfillmentModeResolver::class);
$reloaded = $shipmentRepository->get($shipmentId);
$check('C1 resolver reads persisted OFFLINE', $resolver->forShipment($reloaded) === FulfillmentMode::OFFLINE);

$request->setParams(['shipment' => ['fulfillment_mode' => FulfillmentMode::OFFLINE]]);
$check('C2 request intent detected', $resolver->isOfflineIntent());
$request->setParams(['shipment' => []]);
$check('C3 absent intent is ONLINE', !$resolver->isOfflineIntent());

echo $failures === []
    ? "\nE2E RESULT: ALL PASS\n"
    : "\nE2E RESULT: " . count($failures) . " FAILURE(S)\n";
exit($failures === [] ? 0 : 1);
