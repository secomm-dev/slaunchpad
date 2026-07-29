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

namespace Mirasvit\SeoMarkup\Service;

class HtmlCleanerService
{
    public function clean(string $value): string
    {
        if (!empty($value)) {
            $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $value = preg_replace('/<style[^>]*>[\s\S]*?<\/style>/i', '', $value) ?? $value;
            $value = trim(strip_tags(str_replace('<', ' <', $value)));
            $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        }

        return $value;
    }
}
