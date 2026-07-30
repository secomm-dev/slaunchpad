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
 * @package   mirasvit/module-seo-hyva
 * @version   1.1.1
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



declare(strict_types=1);

namespace Hyva\MirasvitSeoMarkup\Plugin;

use Magento\Theme\Block\Html\Breadcrumbs;
use Mirasvit\SeoMarkup\Model\Config\BreadcrumbListConfig;

/**
 * Hyva renders its own BreadcrumbList ld+json in the breadcrumbs template unless the
 * "skip_ld_json_schema" flag is set on the block. When Mirasvit SeoMarkup renders its
 * own BreadcrumbList rich snippet, the page would otherwise emit two identical
 * BreadcrumbList blocks (flagged by Google Search Console as duplicate breadcrumb
 * markup). The flag cannot be set via layout for every page type (the product page
 * breadcrumbs block ignores catalog_product_view layout arguments), so it is set here
 * on render, gated on the same config that controls Mirasvit's breadcrumb snippet.
 */
class SkipBreadcrumbJsonLdSchema
{
    /**
     * @var BreadcrumbListConfig
     */
    private $breadcrumbListConfig;

    public function __construct(BreadcrumbListConfig $breadcrumbListConfig)
    {
        $this->breadcrumbListConfig = $breadcrumbListConfig;
    }

    public function beforeToHtml(Breadcrumbs $subject)
    {
        if ($this->breadcrumbListConfig->isRsEnabled()) {
            $subject->setData("skip_ld_json_schema", true);
        }

        return null;
    }
}
