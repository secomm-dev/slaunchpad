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

namespace Mirasvit\SeoMarkup\Api\Data;

/**
 * @api
 */
interface ExtenderInterface
{
    public const TABLE_NAME = 'mst_reach_snippet_extender';

    public const REQUEST_PARAM_ID = 'id';

    public const EXTENDER_ID           = 'extender_id';
    public const NAME                  = 'name';
    public const IS_ACTIVE             = 'is_active';
    public const ENTITY_TYPE_ID        = 'entity_type_id';
    public const STORE_IDS             = 'store_ids';
    public const SNIPPET               = 'snippet';
    public const OVERRIDE              = 'override';
    public const CONDITIONS_SERIALIZED = 'conditions_serialized';

    public const SNIPPET_ARRAY = 'snippet_array';

    public const RULE       = 'rule';
    public const CONDITIONS = 'conditions';

    public const PRODUCT_TYPE = 'product';
    public const OFFER_TYPE   = 'offer';

    public const ATTRIBUTES
        = [
            self::EXTENDER_ID,
            self::IS_ACTIVE,
            self::ENTITY_TYPE_ID,
            self::STORE_IDS,
            self::SNIPPET,
            self::OVERRIDE,
            self::CONDITIONS_SERIALIZED,
        ];

    public const ENTITY_TYPES
        = [
            self::PRODUCT_TYPE,
            self::OFFER_TYPE,
        ];

    public const DATA_PERSISTOR_KEY = 'rich_snippet_extender';

    /**
     * @return int|null
     */
    public function getExtenderId(): ?int;

    /**
     * @param int $extenderId
     * @return $this
     */
    public function setExtenderId(int $extenderId): self;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;

    /**
     * @return bool
     */
    public function getIsActive(): bool;

    /**
     * @param bool $isActive
     * @return $this
     */
    public function setIsActive(bool $isActive): self;

    /**
     * @return string
     */
    public function getEntityTypeId(): string;

    /**
     * @param string $entityTypeId
     * @return $this
     */
    public function setEntityTypeId(string $entityTypeId): self;

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
     * @return string
     */
    public function getSnippet(): string;

    /**
     * @param string $snippet
     * @return $this
     */
    public function setSnippet(string $snippet): self;

    /**
     * @return string[]
     */
    public function getSnippetArray(): array;

    /**
     * @param string[] $snippet
     * @return $this
     */
    public function setSnippetArray(array $snippet): self;

    /**
     * @return bool
     */
    public function getOverride(): bool;

    /**
     * @param bool $override
     * @return $this
     */
    public function setOverride(bool $override): self;

    /**
     * @return string|null
     */
    public function getConditionsSerialized(): ?string;

    /**
     * @param string $conditions
     * @return $this
     */
    public function setConditionsSerialized(string $conditions): self;
}
