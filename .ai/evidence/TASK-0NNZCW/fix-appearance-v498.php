<?php
/**
 * TASK-0NNZCW v4.9.8: add data-appearance="default" to the lp-cat-slider html
 * element — PageBuilder stage needs the appearance attribute to instantiate
 * the preview (all other html elements in content have it); without it the
 * Admin stage fails to render the content for editing.
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

file_put_contents('/var/www/projects/slaunchpad/.ai/evidence/TASK-0NNZCW/home-content-before-appearance-fix.txt', $content);

$old = '<div data-content-type="html" data-element="main" class="lp-cat-slider-wrap">';
$new = '<div data-content-type="html" data-appearance="default" data-element="main" class="lp-cat-slider-wrap">';
if (strpos($content, $new) !== false) {
    echo "SKIP: already has data-appearance\n";
    exit(0);
}
if (strpos($content, $old) === false) {
    fwrite(STDERR, "FAIL: cat-slider html element not found in expected form\n");
    exit(1);
}
$page->setContent(str_replace($old, $new, $content));
$om->get(\Magento\Cms\Api\PageRepositoryInterface::class)->save($page);
echo "OK: data-appearance added to lp-cat-slider html element\n";
