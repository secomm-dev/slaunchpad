<?php
/**
 * TASK-0NNZCW v4.9.9: content typography + CTA fixes per Figma specs.
 * - Heading-3 titles (Flash sale): color #1e293b -> #293e2d, lg 32->36, tracking -1 -> -0.5
 * - Big titles (Everyday/REAL SPACES): color -> #293e2d
 * - Sub-20/28 titles (Category/rails/Why us/Journal): color -> black + center (Why us/Journal)
 * - Subtitles #475569 -> black
 * - CTAs: arrow icon (all except hero); View all post secondary -> primary
 */
use Magento\Framework\App\Bootstrap;

require '/var/www/projects/slaunchpad/app/bootstrap.php';
$om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('frontend');

$collection = $om->create(\Magento\Cms\Model\ResourceModel\Page\Collection::class);
$collection->addFieldToFilter('identifier', 'home');
$page = $collection->getFirstItem();
$content = (string) $page->getContent();
file_put_contents('/var/www/projects/slaunchpad/.ai/evidence/TASK-0NNZCW/home-content-before-typography-cta.txt', $content);

$arrow = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>';

$count = 0;

// 1) Title colors: big/heading-3 titles -> brand dark green
$c = str_replace('tracking-[-1px] text-[#1e293b]', 'tracking-[-1px] text-[#293e2d]', $content, $n);
$count += $n;
// remaining plain occurrences on titles
$c = str_replace('leading-[28px] text-[#1e293b]', 'leading-[28px] text-black', $c, $n);
$count += $n;

// 2) Flash title desktop size 32 -> 36, tracking -1 -> -0.5 (heading-3 spec)
$c = str_replace('lg:text-[32px] lg:font-bold lg:leading-[40px] lg:tracking-[-1px]', 'lg:text-[36px] lg:font-bold lg:leading-[40px] lg:tracking-[-0.5px]', $c, $n);
$count += $n;

// 3) Subtitles: gray -> black (design Body-1 black)
$c = str_replace('text-[#475569]', 'text-black', $c, $n);
$count += $n;

// 4) Why us + Journal titles centered
$centers = [
    '!mb-4 font-sans text-[20px] font-medium leading-[28px] text-black lg:!mb-5">Why us?',
    '!mb-4 font-sans text-[20px] font-medium leading-[28px] text-black lg:!mb-5">Journal',
];
foreach ($centers as $needle) {
    $pos = strpos($c, $needle);
    if ($pos !== false) {
        // insert text-center into the class attr of this heading
        $tagStart = strrpos(substr($c, 0, $pos), '<');
        $seg = substr($c, $tagStart, $pos - $tagStart);
        $segNew = preg_replace('/class="/', 'class="text-center ', $seg, 1);
        $c = substr_replace($c, $segNew, $tagStart, strlen($seg));
        $count++;
    }
}

// 5) CTA arrows: append arrow svg before </a> for each CTA anchor (identified by href)
$ctaHrefs = ['/flash-sale', '/collection', '/lookbook', '/sofas', '/blog'];
foreach ($ctaHrefs as $href) {
    $h = 'href="' . $href . '"';
    $pos = strpos($c, $h);
    if ($pos === false) { echo "WARN: href $href not found\n"; continue; }
    $close = strpos($c, '</a>', $pos);
    if ($close === false) continue;
    // only first occurrence per href (subsequent /collection handled by loop over all)
    $c = substr($c, 0, $close) . $arrow . substr($c, $close);
    $count++;
}

// 5b) second /collection button (Explore all + Go to Collection share href)
$pos = strpos($c, 'href="/collection"');
if ($pos !== false) {
    $pos2 = strpos($c, 'href="/collection"', $pos + 10);
    if ($pos2 !== false) {
        $close = strpos($c, '</a>', $pos2);
        $c = substr($c, 0, $close) . $arrow . substr($c, $close);
        $count++;
    }
}

// 6) View all post: secondary -> primary (design: filled green)
$pos = strpos($c, 'href="/blog"');
if ($pos !== false) {
    $tagStart = strrpos(substr($c, 0, $pos), '<a');
    $tagEnd = strpos($c, '>', $pos);
    $tag = substr($c, $tagStart, $tagEnd - $tagStart);
    $tagNew = str_replace(
        ['pagebuilder-button-secondary', 'border border-[#588f60]'],
        ['pagebuilder-button-primary', 'bg-[#588f60]'],
        $tag,
        $n2
    );
    if ($n2 > 0) {
        $c = substr_replace($c, $tagNew, $tagStart, strlen($tag));
        $count++;
    } else {
        echo "WARN: /blog type swap no-op\n";
    }
}

$page->setContent($c);
$om->get(\Magento\Cms\Api\PageRepositoryInterface::class)->save($c ? $page : $page);
echo "OK: $count edits applied, backup saved\n";
