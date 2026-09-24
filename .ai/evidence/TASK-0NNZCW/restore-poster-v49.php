<?php
// TASK-0NNZCW v4.9: restore the `poster` field dropped from the embed_a payload
// by the 04:50 admin save. Surgical replace of the payload attr value only —
// the rest of the user's content edits stay untouched. Backs up first.
use Magento\Framework\App\Bootstrap;

require '/var/www/projects/slaunchpad/app/bootstrap.php';
$om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('frontend');

$collection = $om->create(\Magento\Cms\Model\ResourceModel\Page\Collection::class);
$collection->addFieldToFilter('identifier', 'home');
/** @var \Magento\Cms\Model\Page $page */
$page = $collection->getFirstItem();
$content = (string) $page->getContent();

$backup = '/var/www/projects/slaunchpad/.ai/evidence/TASK-0NNZCW/home-content-before-poster-restore.txt';
file_put_contents($backup, $content);

$canon = file_get_contents('/var/www/projects/slaunchpad/.ai/evidence/TASK-0NNZCW/home-content-v5_6-seeded.html');
preg_match('/\{\{widget[^}]*embed_a[^}]*payload="([^"]+)"/', $content, $mCur);
preg_match('/\{\{widget[^}]*embed_a[^}]*payload="([^"]+)"/', $canon, $mCan);
if (!$mCur || !$mCan) {
    fwrite(STDERR, "FAIL: embed_a directive not found\n");
    exit(1);
}
if (strpos($content, $mCan[1]) !== false) {
    echo "SKIP: payload already contains poster\n";
    exit(0);
}
// sanity: decoded current payload must be the canonical one minus poster
$dec = fn ($s) => json_decode(base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)), true);
$jCur = $dec($mCur[1]);
$jCan = $dec($mCan[1]);
fwrite(STDERR, 'debug: lens ' . strlen($mCur[1]) . '/' . strlen($mCan[1])
    . ' nullCur=' . var_export($jCur === null, true) . ' nullCan=' . var_export($jCan === null, true) . "\n");
unset($jCan['data']['poster']);
fwrite(STDERR, 'debug: equalAfterUnset=' . var_export($jCur == $jCan, true) . "\n");
if ($jCur != $jCan) {
    fwrite(STDERR, "ABORT: payload differs beyond the poster field — manual review needed\n");
    exit(1);
}
$new = str_replace($mCur[1], $mCan[1], $content);
$page->setContent($new);
$om->get(\Magento\Cms\Api\PageRepositoryInterface::class)->save($page);
echo "OK: poster restored (payload swapped, " . strlen($content) . ' -> ' . strlen($new) . " bytes), backup: $backup\n";
