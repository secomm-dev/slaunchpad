<?php
/**
 * TASK-7EYJ4C v2 — re-seed existing footer CMS block rows with the canonical
 * PageBuilder content from Launchpad\CmsContent\Model\FooterBlockContent.
 *
 * The data patch is skip-if-exists (Admin edits survive re-runs), so content
 * changes ship through this script locally: it BACKS UP every affected row
 * (backup-first — aborts before any write if a backup cannot be saved), then
 * updates the content in place, keeping identifiers, titles and store rows.
 *
 * Usage: sudo -u secomm php reseed-footer-pb.php
 */

declare(strict_types=1);

use Launchpad\CmsContent\Model\FooterBlockContent;
use Magento\Framework\App\Bootstrap;

$root = dirname(__DIR__, 3);
require $root . '/app/bootstrap.php';

$_SERVER['HTTP_HOST'] = 'slaunchpad.localhost';
$bootstrap = Bootstrap::create($root, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$objectManager->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');

$resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();
$blockTable = $connection->getTableName('cms_block');
$storeTable = $connection->getTableName('cms_block_store');

$contents = FooterBlockContent::blocks();

// Collect target rows first (identifier + store → block row).
$targets = [];
foreach (array_keys($contents) as $identifier) {
    foreach ([0, 1] as $storeId) {
        $row = $connection->fetchRow(
            $connection->select()
                ->from(['b' => $blockTable], ['block_id', 'title'])
                ->join(['s' => $storeTable], 's.block_id = b.block_id', ['store_id'])
                ->where('b.identifier = ?', $identifier)
                ->where('s.store_id = ?', $storeId)
        );
        if ($row === false) {
            throw new RuntimeException("missing block row for {$identifier} store {$storeId} — run the data patch first");
        }
        $targets["{$identifier}#{$storeId}"] = ['block_id' => (int) $row['block_id'], 'content' => null];
    }
}

// Backup phase — every row, before any write.
foreach ($targets as $key => &$target) {
    $target['content'] = $connection->fetchOne(
        $connection->select()->from($blockTable, ['content'])->where('block_id = ?', $target['block_id'])
    );
    [$identifier, $store] = explode('#', $key);
    $backup = sprintf('%s/block-%s-store%s-before-pb.html', __DIR__, $identifier, $store);
    if (file_put_contents($backup, $target['content']) === false) {
        throw new RuntimeException("cannot write backup {$backup} — aborting, nothing was modified");
    }
    printf("backup %s (%d bytes)\n", basename($backup), strlen($target['content']));
}
unset($target);

// Update phase.
foreach ($contents as $identifier => [, $storeContents]) {
    foreach ($storeContents as $index => $content) {
        $storeId = $index === 0 ? 0 : 1;
        $blockId = $targets[$identifier . '#' . $storeId]['block_id'];
        $connection->update($blockTable, ['content' => $content], ['block_id = ?' => $blockId]);
        printf("updated block %d (%s store %d) -> %d bytes\n", $blockId, $identifier, $storeId, strlen($content));
    }
}
echo "done\n";
