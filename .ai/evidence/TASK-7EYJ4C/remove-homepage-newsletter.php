<?php
/**
 * TASK-7EYJ4C — remove the newsletter row from the CMS page `home` body
 * (the section moves into the global footer).
 *
 * Direct PDO update (admin PageBuilder save is the known payload-stripping trap,
 * TASK-0NNZCW). Backs up both page rows next to this script before updating.
 * The lp-newsletter row is the LAST element of both page contents (verified 09-28);
 * the script asserts the removed fragment contains the Subscribe {{block}} directive.
 *
 * Usage: sudo -u secomm php remove-homepage-newsletter.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$env = require $root . '/app/etc/env.php';
$db = $env['db']['connection']['default'];
$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s', $db['host'], $db['dbname']),
    $db['username'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pages = $pdo->query(
    "SELECT p.page_id, p.identifier, s.store_id, p.content FROM cms_page p
     JOIN cms_page_store s ON s.page_id = p.page_id
     WHERE p.identifier = 'home' AND s.store_id > 0"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($pages as $page) {
    $backup = sprintf('%s/home-content-before-newsletter-move-store%d.html', __DIR__, (int) $page['store_id']);
    if (file_put_contents($backup, $page['content']) === false) {
        // Backup-first: never touch the DB row when the backup cannot be written.
        throw new RuntimeException(sprintf('page %d: cannot write backup %s — aborting', $page['page_id'], $backup));
    }
    printf("page %d (store %d): backup -> %s\n", $page['page_id'], $page['store_id'], basename($backup));

    $pos = strpos($page['content'], '<div class="lp-newsletter');
    if ($pos === false) {
        printf("page %d: no lp-newsletter row found — nothing to do\n", $page['page_id']);
        continue;
    }
    $removed = substr($page['content'], $pos);
    if (!str_contains($removed, 'Launchpad\CmsContent\Block\Newsletter\Subscribe')) {
        throw new RuntimeException(sprintf('page %d: removed fragment missing the Subscribe block directive', $page['page_id']));
    }
    if (strlen($removed) > 6000) {
        throw new RuntimeException(sprintf('page %d: removed fragment unexpectedly large (%d bytes)', $page['page_id'], strlen($removed)));
    }

    $newContent = rtrim(substr($page['content'], 0, $pos));
    $stmt = $pdo->prepare('UPDATE cms_page SET content = ? WHERE page_id = ?');
    $stmt->execute([$newContent, $page['page_id']]);
    printf("page %d: removed %d bytes (lp-newsletter row)\n", $page['page_id'], strlen($removed));
}

echo "done\n";
