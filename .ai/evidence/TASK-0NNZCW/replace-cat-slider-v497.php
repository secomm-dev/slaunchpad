<?php
/**
 * TASK-0NNZCW v4.9.7: replace the Category tiles PB column-group with an
 * HTML-element slider (user: "không dùng column cho category slider" — the
 * slider must bleed to the viewport edge; columns are container-bound).
 * Surgical: swaps ONLY the column-group containing .lp-tile — heading and the
 * rest of the content stay untouched. Backs up first.
 */
use Magento\Framework\App\Bootstrap;

require '/var/www/projects/slaunchpad/app/bootstrap.php';
$om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('frontend');

$collection = $om->create(\Magento\Cms\Model\ResourceModel\Page\Collection::class);
$collection->addFieldToFilter('identifier', 'home');
/** @var \Magento\Cms\Model\Page $page */
$page = $collection->getFirstItem();
$content = (string) $page->getContent();

$backup = '/var/www/projects/slaunchpad/.ai/evidence/TASK-0NNZCW/home-content-before-cat-slider.txt';
file_put_contents($backup, $content);

if (strpos($content, 'lp-tile') === false) {
    echo "SKIP: no lp-tile in content\n";
    exit(0);
}

// locate the column-group that contains lp-tile (balanced <div> scan)
$gi = strpos($content, 'lp-tile');
$gsearch = strrpos($content, 'data-content-type="column-group"', $gi === false ? 0 : $gi - strlen($content) + strlen($content));
$gsearch = strrpos(substr($content, 0, $gi), 'data-content-type="column-group"');
$gstart = strrpos(substr($content, 0, $gsearch), '<div');
$depth = 0;
$gend = null;
if (preg_match_all('/<div\b|<\/div>/', $content, $mm, PREG_OFFSET_CAPTURE, $gstart)) {
    foreach ($mm[0] as [$tag, $off]) {
        if ($tag === '<div') {
            $depth++;
        } else {
            $depth--;
            if ($depth === 0) {
                $gend = $off + strlen($tag);
                break;
            }
        }
    }
}
if (!$gstart || !$gend) {
    fwrite(STDERR, "FAIL: group boundaries not found\n");
    exit(1);
}
$group = substr($content, $gstart, $gend - $gstart);

// extract tiles: image url + label, in order
preg_match_all('/\{\{media url=([^}]+)\}\}/', $group, $imgs);
preg_match_all('/>\s*([A-Z][A-Z ]{1,})\s*</', $group, $lbls);
$imgs = array_values(array_unique($imgs[1]));
$labels = array_map('trim', $lbls[1]);
if (count($imgs) < 6 || count($labels) < 6 || count($imgs) !== count($labels)) {
    fwrite(STDERR, "ABORT: unexpected tile data — imgs=" . count($imgs) . " labels=" . count($labels) . "\n");
    exit(1);
}
// design repeats placeholder tiles past the real ones — add 2 repeats (mock-up)
$tiles = [];
$n = count($labels);
for ($i = 0; $i < $n + 2; $i++) {
    $j = $i % $n;
    $tiles[] = ['img' => $imgs[$j], 'label' => $labels[$j]];
}

$tileHtml = '';
foreach ($tiles as $t) {
    $tileHtml .= '<a href="#" class="lp-cat-tile"><img src="{{media url=' . $t['img'] . '}}" alt="' . $t['label']
        . '" loading="lazy" width="120" height="120"><span class="lp-cat-tile-label">' . $t['label'] . '</span></a>';
}
$replacement = '<div data-content-type="html" data-element="main" class="lp-cat-slider-wrap">'
    . '<div class="lp-cat-slider snap-slider" data-lp-tiles-slider>'
    . '<div class="lp-cat-track snap-track" data-track>' . $tileHtml . '</div>'
    . '</div></div>';

$new = substr_replace($content, $replacement, $gstart, $gend - $gstart);
$page->setContent($new);
$om->get(\Magento\Cms\Api\PageRepositoryInterface::class)->save($page);
echo 'OK: tiles group (' . ($gend - $gstart) . " bytes, 6 columns) -> html slider with " . count($tiles) . " tiles; backup: $backup\n";
