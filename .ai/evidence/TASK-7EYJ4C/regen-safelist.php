<?php
/**
 * TASK-7EYJ4C — regenerate the Tailwind safelist (launchpad-cms.html).
 *
 * Extends the TASK-0NNZCW reseed pattern: extracts every class="…" token from
 * the CMS sources that the Tailwind scanner cannot see, then rewrites
 * web/tailwind/safelist/launchpad-cms.html. Run after any CMS content edit
 * that introduces new Tailwind classes, then rebuild:
 *   cd app/design/frontend/Secomm/launchpad/web/tailwind && npm run build
 *
 * Sources: cms_page `home` (all store rows) + cms_block `homepage-newsletter`
 * (legacy admin artifact) + cms_block footer_* (TASK-7EYJ4C, both stores).
 *
 * Usage: sudo -u secomm php regen-safelist.php
 */

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$env = require $root . '/app/etc/env.php';
$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s', $env['db']['connection']['default']['host'], $env['db']['connection']['default']['dbname']),
    $env['db']['connection']['default']['username'],
    $env['db']['connection']['default']['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$sources = [
    'cms_page:home' => 'SELECT content FROM cms_page WHERE identifier = \'home\'',
    'cms_block:homepage-newsletter' => 'SELECT content FROM cms_block WHERE identifier = \'homepage-newsletter\'',
    'cms_block:footer_*' => "SELECT content FROM cms_block WHERE identifier IN ('footer_newsletter','footer_links','footer_social','footer_trust_payments')",
];

$classes = [];
foreach ($sources as $label => $sql) {
    $count = 0;
    foreach ($pdo->query($sql) as $row) {
        preg_match_all('/class="([^"]*)"/', (string) $row['content'], $matches);
        foreach ($matches[1] as $classAttr) {
            foreach (preg_split('/\s+/', trim($classAttr), -1, PREG_SPLIT_NO_EMPTY) as $class) {
                $classes[$class] = true;
                $count++;
            }
        }
    }
    printf("%-30s scanned\n", $label);
}

$tokens = array_keys($classes);
sort($tokens, SORT_STRING);

$out = "<!-- GENERATED (TASK-0NNZCW + TASK-7EYJ4C): classes used by cms_page `home`,\n";
$out .= "     cms_block `homepage-newsletter` + cms_block `footer_*` (newsletter / links /\n";
$out .= "     social / trust-payments). Regenerate after content edits, then rebuild:\n";
$out .= "     cd app/design/frontend/Secomm/launchpad/web/tailwind && npm run build -->\n";

$perLine = 9;
for ($i = 0; $i < count($tokens); $i += $perLine) {
    $out .= '<div class="' . implode(' ', array_slice($tokens, $i, $perLine)) . "\"></div>\n";
}

$target = $root . '/app/design/frontend/Secomm/launchpad/web/tailwind/safelist/launchpad-cms.html';
if (@file_put_contents($target, $out) === false) {
    throw new RuntimeException("cannot write {$target} — check ownership");
}
printf("\n%d unique classes -> %s\n", count($tokens), $target);
