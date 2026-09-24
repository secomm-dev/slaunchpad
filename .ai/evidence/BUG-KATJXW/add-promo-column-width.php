<?php
// BUG-KATJXW CR1 (SLP-269): thêm width:25% (3/12) vào rule data-pb-style của 4 column
// .lp-promo-card — PB stage đọc width từ style; thiếu ⇒ 0% ⇒ column không chỉnh được.
// Usage: php add-promo-column-width.php [--apply]   (mặc định dry-run; áp cho DB `home` + seed template)
declare(strict_types=1);

$root = dirname(__DIR__, 3);
$apply = in_array('--apply', $argv, true);
$tpl = $root . '/app/code/Launchpad/CmsContent/etc/homepage-content.html';

function addWidth(string $html, string &$log): string
{
    preg_match_all('/<div class="pagebuilder-column lp-promo-card[^"]*"[^>]*data-pb-style="([A-Z0-9]+)"/', $html, $m);
    $ids = $m[1];
    if (count($ids) === 0) {
        $log .= "no promo columns\n";
        return $html;
    }
    foreach ($ids as $id) {
        $pattern = '/(#html-body \[data-pb-style=' . $id . '\]\{)([^}]*)(\})/';
        if (preg_match_all($pattern, $html) !== 1) {
            throw new RuntimeException("rule for $id not found exactly once");
        }
        $html = preg_replace_callback($pattern, static function (array $r) use ($id, &$log): string {
            if (preg_match('/(^|;)\s*width\s*:/', $r[2])) {
                $log .= "$id: width already set ({$r[2]})\n";
                return $r[0];
            }
            $body = str_contains($r[2], 'align-self:stretch')
                ? str_replace('align-self:stretch', 'width:25%;align-self:stretch', $r[2])
                : rtrim($r[2], ';') . ';width:25%';
            $log .= "$id: {$r[2]} => $body\n";
            return $r[1] . $body . $r[3];
        }, $html);
    }
    return $html;
}

$env = include $root . '/app/etc/env.php';
$d = $env['db']['connection']['default'];
$pdo = new PDO("mysql:host={$d['host']};dbname={$d['dbname']}", $d['username'], $d['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$row = $pdo->query("SELECT page_id, content FROM cms_page WHERE identifier = 'home'")->fetch(PDO::FETCH_ASSOC);

$log = "== DB cms_page home (page_id {$row['page_id']})\n";
$newDb = addWidth($row['content'], $log);
$log .= "== seed template\n";
$tplContent = (string) file_get_contents($tpl);
$newTpl = addWidth($tplContent, $log);
echo $log;

if (!$apply) {
    echo "dry-run (pass --apply)\n";
    exit(0);
}
file_put_contents(__DIR__ . '/home-content-before-column-width.txt', $row['content']);
file_put_contents(__DIR__ . '/homepage-content-tpl-before-column-width.html', $tplContent);
if ($newDb !== $row['content']) {
    $stmt = $pdo->prepare('UPDATE cms_page SET content = :content WHERE page_id = :id');
    $stmt->execute(['content' => $newDb, 'id' => $row['page_id']]);
}
if ($newTpl !== $tplContent) {
    file_put_contents($tpl, $newTpl);
}
file_put_contents(__DIR__ . '/home-content-after-column-width.txt', $newDb);
echo "applied\n";
