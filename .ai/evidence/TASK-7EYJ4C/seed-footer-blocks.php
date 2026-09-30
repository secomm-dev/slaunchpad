<?php
/**
 * TASK-7EYJ4C — seed footer CMS blocks locally.
 *
 * Why direct apply (09-29): `bin/magento setup:upgrade` must NOT run right now —
 * `setup:db:status` reports pending DECLARATIVE SCHEMA from the working-tree edit of
 * `app/code/Secomm/Ahamove/etc/db_schema.xml` (another task's work, §12-sensitive).
 * (The earlier 09-28 blocker was the Secomm_VietNamAddress CSV checksum autocrlf issue;
 * the CSVs are LF and git-clean again, so that one no longer blocks.) This script applies
 * Launchpad_CmsContent\Setup\Patch\Data\SeedFooterBlocks::apply() directly so the
 * SLP-275 work can proceed. The patch is already registered in `patch_list`, so when
 * setup:upgrade eventually runs it will NOT re-apply (no risk of clobbering admin edits).
 *
 * Usage: sudo -u secomm php seed-footer-blocks.php [--reset]
 *
 * --reset: revert() first (deletes only the 4 footer_* identifiers this patch owns —
 *          the legacy Hyvä `footer_links_block` row survives, verified 09-29) then
 *          apply() creates them fresh from the current builders. Backup of the pre-reset
 *          rows is written next to this script first, one file per (identifier, store) —
 *          v4.3: filenames now include the store id; the first version wrote one file per
 *          identifier so the store-1 row overwrote the store-0 backup (EN backups lost).
 */

declare(strict_types=1);

use Magento\Framework\App\Bootstrap;

$root = dirname(__DIR__, 3);
require $root . '/app/bootstrap.php';

$_SERVER['HTTP_HOST'] = 'slaunchpad.localhost';
$bootstrap = Bootstrap::create($root, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$objectManager->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');

$patch = $objectManager->create(\Launchpad\CmsContent\Setup\Patch\Data\SeedFooterBlocks::class);

if (in_array('--reset', $argv, true)) {
    $resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
    $connection = $resource->getConnection();
    $rows = $connection->fetchAll(
        $connection->select()
            ->from(['b' => $connection->getTableName('cms_block')], ['block_id', 'identifier', 'content'])
            ->join(['s' => $connection->getTableName('cms_block_store')], 's.block_id = b.block_id', ['store_id'])
            ->where('b.identifier LIKE ?', 'footer\_%')
    );
    foreach ($rows as $row) {
        $backup = sprintf('%s/block-%s-store%d-before-reset.txt', __DIR__, $row['identifier'], $row['store_id']);
        if (file_put_contents($backup, $row['content']) === false) {
            throw new RuntimeException("cannot write backup {$backup} — aborting, nothing deleted");
        }
    }
    echo "backup of ", count($rows), " rows written; reverting...\n";
    $patch->revert();
    echo "reverted.\n";
}

$patch->apply();

$resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();
$rows = $connection->fetchAll(
    $connection->select()
        ->from(['b' => $connection->getTableName('cms_block')], ['block_id', 'identifier', 'title'])
        ->join(['s' => $connection->getTableName('cms_block_store')], 's.block_id = b.block_id', ['store_id'])
        ->where('b.identifier LIKE ?', 'footer_%')
        ->order(['b.identifier', 's.store_id'])
);
foreach ($rows as $row) {
    printf("block %3d | %-25s | %-40s | store %d\n", $row['block_id'], $row['identifier'], $row['title'], $row['store_id']);
}
printf("\n%d footer block rows present\n", count($rows));
