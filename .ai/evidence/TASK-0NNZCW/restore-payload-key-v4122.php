<?php
// TASK-0NNZCW v4.12.2 (09-22): restore the `payload=` attribute NAME on the
// embed_a widget directive. The PB admin round-trip stripped the attribute key
// and left the base64 token bare ({{widget ... schema_version="1" <b64> ...}}),
// so SecommUi::_toHtml() got getData('payload') === '' -> Validator threw ->
// the widget rendered '' and the REAL SPACES video disappeared from the
// storefront. The token itself still decodes to the correct v1 payload
// (poster real-spaces.webp, youtu.be/6HIi9IzqoNM) — only the key is missing.
// Surgical replace of the directive text only; backs up first.
use Magento\Framework\App\Bootstrap;

require '/var/www/projects/slaunchpad/app/bootstrap.php';
$om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('frontend');

$collection = $om->create(\Magento\Cms\Model\ResourceModel\Page\Collection::class);
$collection->addFieldToFilter('identifier', 'home');
/** @var \Magento\Cms\Model\Page $page */
$page = $collection->getFirstItem();
$content = (string) $page->getContent();

$backup = '/var/www/projects/slaunchpad/.ai/evidence/TASK-0NNZCW/home-content-before-payload-key-restore.txt';
file_put_contents($backup, $content);

// strict urlsafe-base64 token, bare between schema_version and type_name
$pattern = '/(schema_version="1") ([A-Za-z0-9_-]+) (type_name=)/';
if (!preg_match($pattern, $content, $m)) {
    fwrite(STDERR, "FAIL: bare embed_a payload token not found (already fixed?)\n");
    exit(1);
}
// sanity: token must decode to a valid embed_a v1 envelope before we touch anything
$dec = fn (string $s) => json_decode(
    base64_decode(strtr($s . str_repeat('=', (4 - strlen($s) % 4) % 4), '-_', '+/'), true),
    true
);
$env = $dec($m[2]);
if (!is_array($env) || ($env['version'] ?? null) !== 1
    || ($env['data']['provider'] ?? '') !== 'youtube'
    || !isset($env['data']['poster'], $env['data']['video_url'])) {
    fwrite(STDERR, "ABORT: bare token does not decode to a valid embed_a payload\n");
    exit(1);
}
$replacement = '$1 payload="$2" $3';
$fixed = preg_replace($pattern, $replacement, $content, 1, $count);
if ($count !== 1) {
    fwrite(STDERR, "FAIL: replacement did not apply exactly once\n");
    exit(1);
}

$page->setContent($fixed)->save();
echo "OK: payload= restored (token kept byte-for-byte, len ", strlen($m[2]), ")\n";
echo "backup: $backup\n";
