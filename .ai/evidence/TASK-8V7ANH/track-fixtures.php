<?php
/**
 * TASK-8V7ANH — add a temporary order-level track to a local test order so the
 * Hyvä order view renders the tracking link (view.phtml renders the link only
 * when getTracksCollection() is non-empty). QC fixture only — delete after verify.
 * Run as secomm: php track-fixtures.php add <orderId> <shipmentId> | del <trackId>
 * (TrackRepository::save requires parent_id = an existing shipment of the order.)
 */
require '/var/www/projects/slaunchpad/app/bootstrap.php';

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;

$om  = (Bootstrap::create(BP, $_SERVER))->getObjectManager();
$om->get(State::class)->setAreaCode('frontend');
$repo = $om->create(\Magento\Sales\Model\Order\Shipment\TrackRepository::class);

$cmd   = $_SERVER['argv'][1] ?? 'add';
$arg   = (int) ($_SERVER['argv'][2] ?? 5);
$ship  = (int) ($_SERVER['argv'][3] ?? 0);

if ($cmd === 'add') {
    $track = $om->create(\Magento\Sales\Model\Order\Shipment\TrackFactory::class)->create();
    $track->setOrderId($arg);
    $track->setParentId($ship);
    $track->setCarrierCode('custom');
    $track->setTitle('QC Verify');
    $track->setTrackNumber('SLP216-' . date('Ymd-His'));
    $track = $repo->save($track);
    printf("added track_id=%s order_id=%s number=%s\n", $track->getEntityId(), $track->getOrderId(), $track->getTrackNumber());
    exit(0);
}

if ($cmd === 'del') {
    $repo->deleteById($arg);
    printf("deleted track_id=%s\n", $arg);
    exit(0);
}

fwrite(STDERR, "unknown cmd {$cmd}\n");
exit(1);
