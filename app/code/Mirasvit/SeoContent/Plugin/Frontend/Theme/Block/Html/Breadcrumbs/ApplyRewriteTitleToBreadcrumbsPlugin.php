<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoContent\Plugin\Frontend\Theme\Block\Html\Breadcrumbs;

use Magento\Framework\View\Element\AbstractBlock;
use Mirasvit\SeoContent\Service\RewriteService;
use ReflectionClass;

/**
 * @see \Magento\Theme\Block\Html\Breadcrumbs::toHtml()
 */
class ApplyRewriteTitleToBreadcrumbsPlugin
{
    private $rewriteService;

    public function __construct(
        RewriteService $rewriteService
    ) {
        $this->rewriteService = $rewriteService;
    }

    public function beforeToHtml(AbstractBlock $subject): void
    {
        $rewrite = $this->rewriteService->getRewrite();

        if (!$rewrite) {
            return;
        }

        if (!$rewrite->getUseInBreadcrumbs()) {
            return;
        }

        $title = $rewrite->getTitle();

        // intentional no-op: rewrite has no custom title ⇒ nothing to apply to breadcrumbs.
        if (empty($title)) {
            return;
        }

        $this->modifyLastCrumbLabel($subject, $title);
    }

    private function modifyLastCrumbLabel(AbstractBlock $subject, string $newLabel): void
    {
        try {
            $reflection = new ReflectionClass($subject);

            $property = $this->findProperty($reflection, '_crumbs');

            if (!$property) {
                return;
            }

            $property->setAccessible(true);
            $crumbs = $property->getValue($subject);

            if (empty($crumbs) || !is_array($crumbs)) {
                return;
            }

            $lastKey = array_key_last($crumbs);

            // intentional no-op: no breadcrumb crumbs present ⇒ nothing to relabel.
            if ($lastKey === null) {
                return;
            }

            $crumbs[$lastKey]['label'] = $newLabel;

            $property->setValue($subject, $crumbs);
        } catch (\ReflectionException $e) {
            // intentional no-op: relabeling breadcrumbs relies on reflecting a core private
            // property; if that shape is unavailable we leave breadcrumbs untouched (best-effort).
            return;
        }
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function findProperty(ReflectionClass $reflection, string $propertyName): ?\ReflectionProperty
    {
        while ($reflection) {
            if ($reflection->hasProperty($propertyName)) {
                return $reflection->getProperty($propertyName);
            }
            $reflection = $reflection->getParentClass();
        }

        return null;
    }
}
