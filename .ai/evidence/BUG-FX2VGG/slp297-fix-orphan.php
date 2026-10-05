<?php
$env = include "/var/www/projects/slaunchpad/app/etc/env.php";
$db = $env["db"]["connection"]["default"];
$pdo = new PDO("mysql:host={$db["host"]};dbname={$db["dbname"]}", $db["username"], $db["password"]);
$backupDir = "/tmp/cms_home_backup_20261002";
@mkdir($backupDir, 0777, true);

$targets = [
    2  => "GEJEEJ3",
    13 => "CVWWU69",
];
foreach ($targets as $pid => $hash) {
    $st = $pdo->prepare("SELECT content FROM cms_page WHERE page_id = ?");
    $st->execute([$pid]);
    $content = $st->fetchColumn();

    $file = "$backupDir/page_$pid.html";
    file_put_contents($file, $content);
    echo "backup page $pid -> $file (", md5($content), ")\n";

    $rule = "#html-body [data-pb-style={$hash}]{display:flex;flex-direction:column}";
    if (strpos($content, $rule) === false) {
        echo "  !! rule not found verbatim for {$hash} — SKIP\n";
        continue;
    }
    $new = str_replace($rule, "", $content);

    $up = $pdo->prepare("UPDATE cms_page SET content = ? WHERE page_id = ?");
    $up->execute([$new, $pid]);
    echo "  fixed page $pid: ", strlen($content), " -> ", strlen($new), " bytes\n";

    preg_match("/<style[^>]*>(.*?)<\/style>/s", $new, $m);
    preg_match_all("/\[data-pb-style=([A-Z0-9]+)\]/", $m[1] ?? "", $sels);
    preg_match_all("/data-pb-style=\"([A-Z0-9]+)\"/", $new, $els);
    $orphan = array_diff(array_unique($sels[1]), array_unique($els[1]));
    echo "  re-diff: rules=", count(array_unique($sels[1])), " elements=", count(array_unique($els[1])), " orphan=", $orphan ? implode(",", $orphan) : "(none)", "\n";
}
echo "DONE\n";
