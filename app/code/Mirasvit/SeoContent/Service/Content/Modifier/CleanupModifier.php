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

namespace Mirasvit\SeoContent\Service\Content\Modifier;

use Mirasvit\SeoContent\Api\Data\ContentInterface;

/**
 * Purpose: Remove non-allowed chars in meta
 */
class CleanupModifier implements ModifierInterface
{
    public function modify(ContentInterface $content, ?string $forceApplyTo = null): ContentInterface
    {
        $metaProperties = [
            ContentInterface::META_TITLE,
            ContentInterface::META_KEYWORDS,
            ContentInterface::META_DESCRIPTION,
        ];

        foreach ($metaProperties as $property) {
            $content->setData($property, $this->cleanupMeta(
                $content->getData($property)
            ));
        }

        return $content;
    }

    private function cleanupMeta(?string $meta): ?string
    {
        if (!$meta) {
            return $meta;
        }

        // Define an array of closing tags that should be replaced with a space
        $tagsToReplaceWithSpace = [
            '</li>', '</br>', '</p>', '</div>',
            '</h1>', '</h2>', '</h3>', '</h4>',
            '</h5>', '</h6>',
        ];

        // Replace each specified closing tag with a space
        foreach ($tagsToReplaceWithSpace as $tag) {
            // Use case-insensitive replacement
            $meta = preg_replace('/' . preg_quote($tag, '/') . '/i', ' ', $meta) ?? $meta;
        }

        // Replace multiple whitespace characters with a single space
        $meta = preg_replace('/\s{2,}/', ' ', $meta) ?? $meta; // Reduce multiple spaces
        $meta = htmlspecialchars($meta, ENT_COMPAT); // Replace double quotes with &quot;

        // Remove <style>...</style> blocks
        $meta = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $meta) ?? $meta;

        // Remove {{widget ... }} placeholders
        $meta = preg_replace('/{{widget[^}]*}}/', '', $meta) ?? $meta;

        // Strip all remaining HTML tags
        $meta = strip_tags($meta);

        // Trim leading and trailing whitespace
        $meta = trim($meta);

        return $meta;
    }
}
