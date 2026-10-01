<?php
/**
 * BUG-6AYPGS (SLP-290) — migrate remaining hex utilities out of cms_page /
 * cms_block content (part 2: homepage content).
 *
 * PageBuilder validate-css-class rejects "#" — element css_classes containing
 * bg-[#...]/text-[#...] block the admin save. Token-aware replacement
 * (leading-space form only, so "hover:bg-[#...]" stays intact) to semantic
 * classes styled in homepage.css:
 *   text-[#293e2d] -> lp-text-olive
 *   bg-[#304b34]   -> lp-bg-olive-dark
 *   bg-[#45744c]   -> lp-bg-olive
 *   bg-[#e7f1e8]   -> lp-bg-mint
 *
 * Backup-first per row: aborts before any write if a backup cannot be saved.
 * Raw-HTML hexes (pagebuilder-button-* anchors, newsletter form) are left
 * untouched — they never pass through the CSS Classes field.
 *
 * Usage: sudo -u secomm php migrate-home-hex-classes-slp290.php
 */

declare(strict_types=1);

use Magento\Framework\App\Bootstrap;

$root = dirname(__DIR__, 3);
require $root . '/app/bootstrap.php';

$_SERVER['HTTP_HOST'] = 'slaunchpad.localhost';
$bootstrap = Bootstrap::create($root, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$objectManager->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');

$connection = $objectManager->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();

$map = [
    ' text-[#293e2d]' => ' lp-text-olive',
    ' bg-[#304b34]' => ' lp-bg-olive-dark',
    ' bg-[#45744c]' => ' lp-bg-olive',
    ' bg-[#e7f1e8]' => ' lp-bg-mint',
];

$queries = [
    'cms_page' => 'SELECT b.page_id id, b.identifier, s.store_id, b.content FROM cms_page b JOIN cms_page_store s ON s.page_id = b.page_id',
    'cms_block' => 'SELECT b.block_id id, b.identifier, s.store_id, b.content FROM cms_block b LEFT JOIN cms_block_store s ON s.block_id = b.block_id',
];

foreach ($queries as $table => $sql) {
    $rows = $connection->fetchAll($sql);
    foreach ($rows as $row) {
        $content = (string) $row['content'];
        $updated = strtr($content, $map);
        if ($updated === $content) {
            continue;
        }
        $backup = sprintf('%s/%s-%s-store%s-before.html', __DIR__, $table, $row['identifier'], $row['store_id'] ?? 'null');
        if (file_put_contents($backup, $content) === false) {
            throw new RuntimeException("cannot write backup {$backup} — aborting, nothing was modified");
        }
        $where = $table === 'cms_page' ? ['page_id = ?' => (int) $row['id']] : ['block_id = ?' => (int) $row['id']];
        $connection->update($table, ['content' => $updated], $where);
        printf(
            "migrated %s #%d [%s] store=%s -> backup %s%s",
            $table,
            (int) $row['id'],
            $row['identifier'],
            $row['store_id'] ?? 'null',
            basename($backup),
            PHP_EOL
        );
    }
}
echo "done\n";
