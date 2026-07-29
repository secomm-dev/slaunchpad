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

namespace Mirasvit\SeoSitemap\Api\Data;

/**
 * @api
 */
interface ProviderInterface
{
    public const TABLE_NAME = 'mst_seo_sitemap_provider';

    public const ID         = 'provider_id';
    public const NAME       = 'name';
    public const URL        = 'url';
    public const PRIORITY   = 'priority';
    public const FREQUENCY  = 'frequency';
    public const IS_ACTIVE  = 'is_active';
    public const STORE_IDS  = 'store_ids';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * @return int|null
     */
    public function getId(): ?int;

    /**
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * @param string|null $name
     * @return $this
     */
    public function setName(?string $name): self;

    /**
     * @return string|null
     */
    public function getUrl(): ?string;

    /**
     * @param string|null $url
     * @return $this
     */
    public function setUrl(?string $url): self;

    /**
     * @return string|null
     */
    public function getPriority(): ?string;

    /**
     * @param string|null $priority
     * @return $this
     */
    public function setPriority(?string $priority): self;

    /**
     * @return string|null
     */
    public function getFrequency(): ?string;

    /**
     * @param string|null $frequency
     * @return $this
     */
    public function setFrequency(?string $frequency): self;

    /**
     * @return int|null
     */
    public function getIsActive(): ?int;

    /**
     * @param int|null $isActive
     * @return $this
     */
    public function setIsActive(?int $isActive): self;

    /**
     * @return int[]
     */
    public function getStoreIds(): array;

    /**
     * @param int[] $storeIds
     * @return $this
     */
    public function setStoreIds(array $storeIds): self;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string;
}
