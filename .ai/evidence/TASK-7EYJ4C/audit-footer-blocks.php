<?php
/** TASK-7EYJ4C — audit footer block rows (version fingerprints + store relations). */
declare(strict_types=1);

$env = require '/var/www/projects/slaunchpad/app/etc/env.php';
$pdo = new PDO(
    "mysql:host={$env['db']['connection']['default']['host']};dbname={$env['db']['connection']['default']['dbname']}",
    $env['db']['connection']['default']['username'],
    $env['db']['connection']['default']['password']
);

$rows = $pdo->query(
    "SELECT b.block_id, b.identifier, s.store_id, b.content, b.update_time
     FROM cms_block b JOIN cms_block_store s ON s.block_id = b.block_id
     WHERE b.identifier LIKE 'footer\\_%' ORDER BY b.block_id"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    $c = $r['content'];
    $html = substr_count($c, 'data-content-type="html"');
    $cols = preg_match_all('/data-content-type="column" data-appearance="full-height"/', $c);
    $badCols = preg_match_all('/data-content-type="column" data-appearance="(?!full-height)/', $c);
    $v4styles = (str_contains($c, 'text-[#4a5565]') || str_contains($c, 'font-medium')) ? 'Y' : '-';
    printf(
        "#%d %-22s store%d | html=%d col-full-height=%d col-bad=%d v4-styles=%s | %s\n",
        $r['block_id'], $r['identifier'], $r['store_id'], $html, $cols, $badCols, $v4styles, $r['update_time']
    );
}
echo count($rows), " rows\n";
