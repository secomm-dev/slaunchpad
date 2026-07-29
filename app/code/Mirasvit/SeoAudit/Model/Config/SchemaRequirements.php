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

namespace Mirasvit\SeoAudit\Model\Config;

/**
 * Google's required properties per schema.org @type, scoped to the types
 * Mirasvit\SeoMarkup actually emits (Block\Rs\*.php). Not a full schema.org
 * vocabulary — merchant-added Extender types we don't recognize are skipped
 * by the check rather than guessed at here.
 */
class SchemaRequirements
{
    /**
     * @return array<string, array{required?: string[], anyOf?: string[][]}>
     */
    public function getAll(): array
    {
        return [
            'Product'        => [
                'required' => ['name', 'image'],
                'anyOf'    => [['offers', 'review', 'aggregateRating']],
            ],
            'ProductGroup'   => [
                'required' => ['name', 'image'],
                'anyOf'    => [['offers', 'review', 'aggregateRating']],
            ],
            'Organization'   => [
                'required' => ['name', 'url'],
            ],
            'Brand'          => [
                'required' => ['name'],
            ],
            'BreadcrumbList' => [
                'required' => ['itemListElement'],
            ],
            'WebSite'        => [
                'required' => ['url', 'potentialAction'],
            ],
            'OfferCatalog'   => [
                'required' => ['name', 'url', 'itemListElement'],
            ],
            'ItemList'       => [
                'required' => ['name', 'url', 'itemListElement'],
            ],
            'WebPage'        => [
                'required' => ['name'],
            ],
            'AboutPage'      => [
                'required' => ['name'],
            ],
            'ContactPage'    => [
                'required' => ['name'],
            ],
            'Article'        => [
                'required' => ['headline'],
            ],
            'BlogPosting'    => [
                'required' => ['headline'],
            ],
        ];
    }

    public function get(string $type): ?array
    {
        return $this->getAll()[$type] ?? null;
    }
}
