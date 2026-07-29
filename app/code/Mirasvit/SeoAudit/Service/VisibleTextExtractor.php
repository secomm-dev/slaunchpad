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

namespace Mirasvit\SeoAudit\Service;

use DOMDocument;
use Mirasvit\SeoAudit\Api\Data\UrlInterface;

class VisibleTextExtractor
{
    /**
     * Structural/non-content tags stripped before counting words, so nav/footer
     * boilerplate (present on every page regardless of actual content) doesn't
     * mask a genuinely thin product/category description.
     */
    private const EXCLUDED_TAGS = ['script', 'style', 'nav', 'header', 'footer', 'noscript'];

    public function extractWordCount(UrlInterface $url): int
    {
        $content = $url->getContent();

        if (!$content) {
            return 0;
        }

        $dom = new DOMDocument();

        libxml_use_internal_errors(true);
        $dom->loadHTML($content);
        libxml_clear_errors();

        foreach (self::EXCLUDED_TAGS as $tag) {
            $nodes = [];
            foreach ($dom->getElementsByTagName($tag) as $node) {
                $nodes[] = $node;
            }
            foreach ($nodes as $node) {
                if ($node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $text = $body ? $body->textContent : $dom->textContent;
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '') {
            return 0;
        }

        $words = preg_split('/\s+/u', $text);

        return $words === false ? 0 : count($words);
    }
}
