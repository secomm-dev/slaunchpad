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

namespace Mirasvit\SeoContent\Api\Data;

/**
 * @api
 */
interface RewriteInterface extends ContentInterface
{
    const TABLE_NAME = 'mst_seo_content_rewrite';

    const ID = 'rewrite_id';
    const URL = 'url';
    const IS_ACTIVE = 'is_active';
    const SORT_ORDER = 'sort_order';
    const STORE_IDS = 'store_ids';
    const ADD_TO_SITEMAP = 'add_to_sitemap';
    const USE_IN_BREADCRUMBS = 'use_in_breadcrumbs';

    const SITEMAP_DEFAULT = 0;
    const SITEMAP_YES = 1;
    const SITEMAP_NO = 2;

    const META_ROBOTS_DEFAULT = '-';
    const META_ROBOTS_NOINDEX_NOFOLLOW = 'noindex,nofollow';
    const META_ROBOTS_NOINDEX_FOLLOW = 'noindex,follow';
    const META_ROBOTS_INDEX_NOFOLLOW = 'index,nofollow';
    const META_ROBOTS_INDEX_FOLLOW = 'index,follow';

    /**
     * @return int|null
     */
    public function getId();

    /**
     * @param string $value
     * @return $this
     */
    public function setUrl($value);

    /**
     * @return string|null
     */
    public function getUrl();

    /**
     * @param bool $value
     * @return $this
     */
    public function setIsActive($value);

    /**
     * @return bool|null
     */
    public function getIsActive();

    /**
     * @param string $value
     * @return $this
     */
    public function setSortOrder($value);

    /**
     * @return string|null
     */
    public function getSortOrder();

    /**
     * @param int[] $value
     * @return $this
     */
    public function setStoreIds(array $value);

    /**
     * @return int[]
     */
    public function getStoreIds();

    /**
     * @param int $value
     * @return $this
     */
    public function setAddToSitemap(int $value): self;

    /**
     * @return int
     */
    public function getAddToSitemap(): int;

    /**
     * @param bool $value
     * @return $this
     */
    public function setUseInBreadcrumbs(bool $value): self;

    /**
     * @return bool
     */
    public function getUseInBreadcrumbs(): bool;
}
