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

namespace Mirasvit\SeoAutolink\Api\Data;

/**
 * @api
 */
interface LinkInterface
{
    const TABLE_NAME = 'mst_seoautolink_link';

    const ID               = 'link_id';
    const KEYWORD          = 'keyword';
    const URL              = 'url';
    const URL_TARGET       = 'url_target';
    const URL_TITLE        = 'url_title';
    const IS_NOFOLLOW      = 'is_nofollow';
    const MAX_REPLACEMENTS = 'max_replacements';
    const SORT_ORDER       = 'sort_order';
    const OCCURENCE        = 'occurence';
    const IS_ACTIVE        = 'is_active';
    const ACTIVE_FROM      = 'active_from';
    const ACTIVE_TO        = 'active_to';
    const STORE_IDS        = 'store_ids';
    const CREATED_AT       = 'created_at';
    const UPDATED_AT       = 'updated_at';
    const LINK_TYPE        = 'link_type';
    const ENTITY_ID        = 'entity_id';

    const LINK_TYPE_URL      = 'url';
    const LINK_TYPE_PRODUCT  = 'product';
    const LINK_TYPE_CATEGORY = 'category';

    /**
     * @return int|null
     */
    public function getId();

    /**
     * @return string|null
     */
    public function getKeyword(): ?string;

    /**
     * @param string|null $value
     * @return $this
     */
    public function setKeyword(?string $value): self;

    /**
     * @return string|null
     */
    public function getUrl(): ?string;

    /**
     * @param string|null $value
     * @return $this
     */
    public function setUrl(?string $value): self;

    /**
     * @return string|null
     */
    public function getUrlTarget(): ?string;

    /**
     * @param string|null $value
     * @return $this
     */
    public function setUrlTarget(?string $value): self;

    /**
     * @return string|null
     */
    public function getUrlTitle(): ?string;

    /**
     * @param string|null $value
     * @return $this
     */
    public function setUrlTitle(?string $value): self;

    /**
     * @return int|null
     */
    public function getIsNofollow(): ?int;

    /**
     * @param int|null $value
     * @return $this
     */
    public function setIsNofollow(?int $value): self;

    /**
     * @return int|null
     */
    public function getMaxReplacements(): ?int;

    /**
     * @param int|null $value
     * @return $this
     */
    public function setMaxReplacements(?int $value): self;

    /**
     * @return int|null
     */
    public function getSortOrder(): ?int;

    /**
     * @param int|null $value
     * @return $this
     */
    public function setSortOrder(?int $value): self;

    /**
     * @return int|null
     */
    public function getOccurence(): ?int;

    /**
     * @param int|null $value
     * @return $this
     */
    public function setOccurence(?int $value): self;

    /**
     * @return int|null
     */
    public function getIsActive(): ?int;

    /**
     * @param int|null $value
     * @return $this
     */
    public function setIsActive(?int $value): self;

    /**
     * @return string|null
     */
    public function getActiveFrom(): ?string;

    /**
     * @param string|null $value
     * @return $this
     */
    public function setActiveFrom(?string $value): self;

    /**
     * @return string|null
     */
    public function getActiveTo(): ?string;

    /**
     * @param string|null $value
     * @return $this
     */
    public function setActiveTo(?string $value): self;

    /**
     * @return int[]
     */
    public function getStoreIds(): array;

    /**
     * @param int[] $value
     * @return $this
     */
    public function setStoreIds(array $value): self;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * @return string
     */
    public function getLinkType(): string;

    /**
     * @param string $value
     * @return $this
     */
    public function setLinkType(string $value): self;

    /**
     * @return int|null
     */
    public function getLinkEntityId(): ?int;

    /**
     * @param int|null $value
     * @return $this
     */
    public function setLinkEntityId(?int $value): self;
}
