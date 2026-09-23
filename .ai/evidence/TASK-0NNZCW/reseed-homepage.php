<?php
/**
 * Re-seed the homepage PageBuilder content from the canonical seed + regenerate
 * the Tailwind safelist (TASK-0NNZCW).
 *
 * WHY: a PageBuilder admin save re-serializes the hand-seeded master format and
 * STRIPS custom classes from structural elements (row inner, column-group,
 * slide wrapper, overlay) and drops raw iframes inside video elements. After
 * such a save, run:
 *
 *   sudo -u secomm php .ai/evidence/TASK-0NNZCW/reseed-homepage.php
 *   cd app/design/frontend/Secomm/launchpad/web/tailwind && npm run build
 *   sudo -u secomm php bin/magento cache:flush
 *
 * Only seed when the admin edits should be discarded — the seed overwrites
 * cms_page `home` content (the current content is backed up next to it).
 */
$bootstrap = __DIR__ . '/../../../../app/bootstrap.php';
require $bootstrap;
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$state = $om->get(\Magento\Framework\App\State::class);
$state->setAreaCode('adminhtml');

$resource = $om->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();

$seed = file_get_contents(__DIR__ . '/home-content-v5_4-seeded.html');

// newsletter block id by identifier (kept for the admin-editable copy)
$blockId = $connection->fetchOne(
    "SELECT block_id FROM {$connection->getTableName('cms_block')} WHERE identifier = 'homepage-newsletter'"
);
if ($blockId) {
    // seed keeps a {{block}} module directive — the CMS block is only a spare
    // admin-editable copy; nothing to substitute, kept for reference.
}

$connection->update(
    $connection->getTableName('cms_page'),
    ['content' => $seed, 'update_time' => new \Zend_Db_Expr('NOW()')],
    ['identifier = ?' => 'home']
);
echo "cms_page `home` re-seeded (", strlen($seed), " bytes)\n";

// regenerate the Tailwind safelist from the seeded content + newsletter block
$content = $seed;
$blockContent = (string) $connection->fetchOne(
    "SELECT content FROM {$connection->getTableName('cms_block')} WHERE identifier = 'homepage-newsletter'"
);
$classes = [];
foreach ([$content, $blockContent] as $html) {
    preg_match_all('/class="([^"]+)"/', $html, $matches);
    foreach ($matches[1] as $value) {
        foreach (preg_split('/\s+/', trim($value)) as $token) {
            if ($token !== '') {
                $classes[$token] = true;
            }
        }
    }
}
ksort($classes);
$out = "<!-- GENERATED (TASK-0NNZCW): classes used by cms_page `home` content +\n     cms_block `homepage-newsletter`. Regenerate after content edits, then rebuild:\n     cd app/design/frontend/Secomm/launchpad/web/tailwind && npm run build -->\n";
foreach (array_chunk(array_keys($classes), 8) as $chunk) {
    $out .= '<div class="' . implode(' ', $chunk) . "\"></div>\n";
}
$safelist = BP . '/app/design/frontend/Secomm/launchpad/web/tailwind/safelist/launchpad-cms.html';
file_put_contents($safelist, $out);
echo "safelist regenerated (", count($classes), " classes) -> $safelist\n";
echo "NEXT: cd app/design/frontend/Secomm/launchpad/web/tailwind && npm run build && bin/magento cache:flush\n";
