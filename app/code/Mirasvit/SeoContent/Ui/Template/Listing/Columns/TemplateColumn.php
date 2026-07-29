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

namespace Mirasvit\SeoContent\Ui\Template\Listing\Columns;

use Magento\Ui\Component\Listing\Columns\Column;
use Mirasvit\SeoContent\Api\Data\TemplateInterface;

class TemplateColumn extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $item[$this->getName()] = $this->renderTemplate($item);
            }
        }

        return $dataSource;
    }

    private function renderTemplate(array $template): string
    {
        $html = '<div class="mst-seo-content__template-listing-column-template">';

        $values = [
            TemplateInterface::META_TITLE           => __('Meta title'),
            TemplateInterface::META_KEYWORDS        => __('Meta keywords'),
            TemplateInterface::META_DESCRIPTION     => __('Meta description'),
            TemplateInterface::TITLE                => __('Title (H1)'),
            TemplateInterface::DESCRIPTION          => __('SEO description'),
            TemplateInterface::SHORT_DESCRIPTION    => __('Product short description'),
            TemplateInterface::FULL_DESCRIPTION     => __('Product Description'),
            TemplateInterface::CATEGORY_DESCRIPTION => __('Category description'),
            TemplateInterface::BRAND_DESCRIPTION    => __('Brand Description'),
        ];

        foreach ($values as $key => $label) {
            $value = (string)$template[$key];
            if (!trim($value)) {
                continue;
            }

            $value = $this->highlightTags($value);
            $html  .= "<p><u>$label:</u> $value</p>";
        }

        $html .= '</div>';

        return $html;
    }

    public function highlightTags(string $string): string
    {
        $string = preg_replace('/(<img([^>]*)>)/', "&ltimg $2 &gt", $string) ?? $string;

        return preg_replace('/([{\[][a-zA-Z_|]+[}\]])/', "<span class='highlight'>$1</span>", $string) ?? '';
    }
}
