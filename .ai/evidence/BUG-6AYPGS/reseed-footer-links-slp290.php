<?php
/**
 * BUG-6AYPGS (SLP-290) — update existing footer_links CMS block rows with the
 * canonical PageBuilder content after the hex-color utilities were moved out
 * of the content (PageBuilder validate-css-class rejects "#").
 *
 * Mirrors the TASK-7EYJ4C reseed pattern: backup-first — aborts before any
 * write if a backup cannot be saved. Store map follows SeedFooterBlocks:
 * index 0 → store 4 (English), index 1 → store 1 (Tiếng Việt); a store view
 * without an existing row is skipped (nothing to update, matches the
 * skip-if-missing seed contract).
 *
 * Usage: sudo -u secomm php reseed-footer-links-slp290.php
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

$storeMap = [0 => 4, 1 => 1]; // index 0 → EN (store 4), index 1 → VI (store 1)
$contents = FooterBlockContent::blocks()['footer_links'][1];

foreach ($storeMap as $index => $storeId) {
    $row = $connection->fetchRow(
        $connection->select()
            ->from(['b' => $blockTable], ['block_id', 'title'])
            ->join(['s' => $storeTable], 's.block_id = b.block_id', ['store_id'])
            ->where('b.identifier = ?', 'footer_links')
            ->where('s.store_id = ?', $storeId)
    );
    if ($row === false) {
        printf("skip: no footer_links row for store %d (nothing to update)%s", $storeId, PHP_EOL);
        continue;
    }

    $oldContent = (string) $connection->fetchOne(
        $connection->select()->from($blockTable, ['content'])->where('block_id = ?', $row['block_id'])
    );
    $backup = sprintf('%s/block-footer_links-store%s-before.html', __DIR__, $storeId);
    if (file_put_contents($backup, $oldContent) === false) {
        throw new RuntimeException("cannot write backup {$backup} — aborting, nothing was modified");
    }

    $connection->update(
        $blockTable,
        ['content' => $contents[$index]],
        ['block_id = ?' => (int) $row['block_id']]
    );
    printf(
        "updated block_id=%d store=%d (%s): %d -> %d bytes, backup %s%s",
        (int) $row['block_id'],
        $storeId,
        (string) $row['title'],
        strlen($oldContent),
        strlen($contents[$index]),
        basename($backup),
        PHP_EOL
    );
}
