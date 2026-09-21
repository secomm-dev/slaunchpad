<?php
/**
 * TASK-0NNZCW v4.9.9b: (1) center Why us?/Journal titles in content;
 * (2) regenerate the Tailwind safelist from the live DB content + newsletter
 * block — new content classes (lg:text-[36px], text-center...) only compile
 * after this regen + rebuild.
 */
use Magento\Framework\App\Bootstrap;

require '/var/www/projects/slaunchpad/app/bootstrap.php';
$om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('frontend');

$collection = $om->create(\Magento\Cms\Model\ResourceModel\Page\Collection::class);
$collection->addFieldToFilter('identifier', 'home');
$page = $collection->getFirstItem();
$content = (string) $page->getContent();
file_put_contents('/var/www/projects/slaunchpad/.ai/evidence/TASK-0NNZCW/home-content-before-center.txt', $content);

// (1) center Why us? / Journal headings — anchor: end of heading tag
foreach (['Why us?', 'Journal'] as $title) {
    $needle = '>Why us?';
    $needle = '>' . $title . '</h2>';
    $pos = strpos($content, $needle);
    if ($pos === false) { echo "WARN: heading '$title' not found\n"; continue; }
    $tagStart = strrpos(substr($content, 0, $pos), '<h2');
    $classPos = strpos($content, 'class="', $tagStart);
    if ($classPos === false || $classPos > $pos) { echo "WARN: class attr not found for '$title'\n"; continue; }
    $insertAt = $classPos + strlen('class="');
    // idempotent
    if (substr($content, $insertAt, 12) === 'text-center ') {
        echo "skip (already centered): $title\n";
        continue;
    }
    $content = substr_replace($content, 'text-center ', $insertAt, 0);
    echo "centered: $title\n";
}

$connection = $om->get(\Magento\Framework\App\ResourceConnection::class)->getConnection();
$page->setContent($content);
$om->get(\Magento\Cms\Api\PageRepositoryInterface::class)->save($page);
echo "content saved\n";

// (2) safelist regen from live content + newsletter cms block
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
echo 'safelist regenerated (', count($classes), " classes)\n";
