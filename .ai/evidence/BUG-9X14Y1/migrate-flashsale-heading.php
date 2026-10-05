<?php
/**
 * BUG-9X14Y1 content migration — heading PB riêng → title-in-widget.
 * Áp cho: fixture etc/homepage-content.html + các page DB có widget Flash Sale.
 * Mỗi page: bỏ <h2 data-content-type="heading"> đứng ngay trước wrapper html,
 * set title="TEXT" vào widget directive, bỏ class="!mt-5" khỏi wrapper.
 * Run as secomm: php migrate-flashsale-heading.php --fixture | --db
 */
declare(strict_types=1);

function migrate(string $content, string $label): string
{
    $needle = 'FlashSaleList';
    $cnt = substr_count($content, $needle);
    if ($cnt !== 1) {
        throw new RuntimeException("$label: expected exactly 1 widget occurrence, got $cnt");
    }
    $dpos = (int) stripos($content, $needle);
    $dstart = strrpos(substr($content, 0, $dpos), '{{widget');
    $dend = (int) strpos($content, '}}', $dpos) + 2;
    $directive = substr($content, $dstart, $dend - $dstart);
    if (stripos($directive, 'title=') !== false) {
        throw new RuntimeException("$label: directive already has title");
    }
    // Wrapper html div = <div> gần nhất trước directive
    $divStart = strrpos(substr($content, 0, $dstart), '<div');
    $openTag = substr($content, $divStart, $dstart - $divStart);
    if (!str_contains($openTag, 'data-content-type="html"')) {
        throw new RuntimeException("$label: nearest div before widget is not the html wrapper");
    }
    // Heading phải đứng NGAY TRƯỚC wrapper: '</h2><div class="!mt-5"'
    if (substr($content, $divStart - 5, 5) !== '</h2>') {
        throw new RuntimeException("$label: no <h2> immediately before the html wrapper");
    }
    $h2Close = $divStart - 5;
    $h2Start = strrpos(substr($content, 0, $h2Close), '<h2');
    $h2TagEnd = (int) strpos($content, '>', $h2Start) + 1;
    $text = substr($content, $h2TagEnd, $h2Close - $h2TagEnd);
    if ($text === '' || str_contains($text, '<')) {
        throw new RuntimeException("$label: unexpected heading text: " . var_export($text, true));
    }

    $openTagNew = str_replace(' class="!mt-5"', '', $openTag);
    if ($openTagNew === $openTag) {
        throw new RuntimeException("$label: wrapper class !mt-5 not found");
    }
    $tplAttr = 'template="Launchpad_CmsContent::product/widget/flash-sale.phtml"';
    $directiveNew = str_replace($tplAttr, $tplAttr . ' title="' . $text . '"', $directive);
    if ($directiveNew === $directive) {
        throw new RuntimeException("$label: template attr not found in directive");
    }

    $new = substr($content, 0, $h2Start)
        . $openTagNew
        . $directiveNew
        . substr($content, $dend);
    foreach ([
        'data-content-type="html"' => 'wrapper div present',
        'title="' . $text . '"' => 'title inserted',
    ] as $expect => $msg) {
        if (!str_contains($new, $expect)) {
            throw new RuntimeException("$label: post-assert failed — $msg");
        }
    }
    echo "$label: heading \"$text\" -> widget title OK (content " . strlen($content) . ' -> ' . strlen($new) . " bytes)\n";
    return $new;
}

$target = $argv[1] ?? '';
if ($target === '--fixture') {
    $path = '/var/www/projects/slaunchpad/app/code/Launchpad/CmsContent/etc/homepage-content.html';
    $content = (string) file_get_contents($path);
    file_put_contents($path, migrate($content, 'FIXTURE'));
    echo "FIXTURE written\n";
    exit(0);
}

if ($target === '--db') {
    $env = include '/var/www/projects/slaunchpad/app/etc/env.php';
    $c = $env['db']['connection']['default'];
    $pdo = new PDO(
        "mysql:host={$c['host']};dbname={$c['dbname']}",
        $c['username'],
        $c['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $ids = $pdo->query("SELECT page_id FROM cms_page WHERE content LIKE '%FlashSaleList%'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $pid) {
        $content = $pdo->query("SELECT content FROM cms_page WHERE page_id=$pid")->fetchColumn();
        $new = migrate((string) $content, "PAGE #$pid");
        $st = $pdo->prepare('UPDATE cms_page SET content=? WHERE page_id=?');
        $st->execute([$new, $pid]);
        echo "PAGE #$pid written\n";
    }
    echo 'DB pages migrated: ' . count($ids) . "\n";
    exit(0);
}
echo "usage: php migrate-flashsale-heading.php --fixture|--db\n";
exit(2);
