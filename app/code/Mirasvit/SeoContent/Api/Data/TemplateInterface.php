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
interface TemplateInterface extends ContentInterface
{
    const RULE_TYPE_PRODUCT    = 1;
    const RULE_TYPE_CATEGORY   = 2;
    const RULE_TYPE_NAVIGATION = 3;
    const RULE_TYPE_PAGE       = 4;
    const RULE_TYPE_BLOG       = 5;
    const RULE_TYPE_BRAND      = 6;
    const RULE_TYPE_LANDING    = 7;

    const TABLE_NAME = 'mst_seo_content_template';

    const ID                         = 'template_id';
    const RULE_TYPE                  = 'rule_type';
    const NAME                       = 'name';
    const IS_ACTIVE                  = 'is_active';
    const SORT_ORDER                 = 'sort_order';
    const CONDITIONS_SERIALIZED      = 'conditions_serialized';
    const ACTIONS_SERIALIZED         = 'actions_serialized';
    const STOP_RULE_PROCESSING       = 'stop_rules_processing';
    const APPLY_FOR_CHILD_CATEGORIES = 'apply_for_child_categories';
    const APPLY_FOR_HOMEPAGE         = 'apply_for_homepage';
    const STORE_IDS                  = 'store_ids';
    const APPLY_FOR_ALL_BRANDS_PAGE  = 'apply_for_all_brands_page';
    const APPLY_FOR_LANDING_PAGES    = 'apply_for_landing_pages';

    /**
     * @return int|null
     */
    public function getId();

    /**
     * @param int $value
     * @return $this
     */
    public function setRuleType(int $value): self;

    /**
     * @return int|null
     */
    public function getRuleType(): ?int;

    /**
     * @param string $value
     * @return $this
     */
    public function setName(string $value): self;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param bool $value
     * @return $this
     */
    public function setIsActive(bool $value): self;

    /**
     * @return bool
     */
    public function getIsActive(): bool;

    /**
     * @param int $value
     * @return $this
     */
    public function setSortOrder(int $value): self;

    /**
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * @param bool $value
     * @return $this
     */
    public function setStopRulesProcessing(bool $value): self;

    /**
     * @return bool
     */
    public function getStopRulesProcessing(): bool;

    /**
     * @param bool $value
     * @return $this
     */
    public function setApplyForChildCategories(bool $value): self;

    /**
     * @return bool
     */
    public function getApplyForChildCategories(): bool;

    /**
     * @return string|null
     */
    public function getConditionsSerialized(): ?string;

    /**
     * @param string $value
     * @return $this
     */
    public function setConditionsSerialized(string $value): self;

    /**
     * @param int[] $value
     * @return $this
     */
    public function setStoreIds(array $value): self;

    /**
     * @return int[]
     */
    public function getStoreIds(): array;

    /**
     * @param bool $value
     * @return $this
     */
    public function setApplyForHomepage(bool $value): self;

    /**
     * @return bool
     */
    public function getApplyForHomepage(): bool;

    /**
     * @param bool $value
     * @return $this
     */
    public function setApplyForAllBrandsPage(bool $value): self;

    /**
     * @return bool
     */
    public function getApplyForAllBrandsPage(): bool;

    /**
     * @param bool $value
     * @return $this
     */
    public function setApplyForLandingPages(bool $value): self;

    /**
     * @return bool
     */
    public function getApplyForLandingPages(): bool;
}
