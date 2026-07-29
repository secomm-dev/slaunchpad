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

namespace Mirasvit\Seo\Api\Data;

/**
 * @api
 */
interface CanonicalRewriteInterface
{
    const TABLE_NAME = 'mst_seo_canonical_rewrite';

    const ID = 'canonical_rewrite_id';
    const IS_ACTIVE = 'is_active';
    const CANONICAL = 'canonical';
    const REG_EXPRESSION = 'reg_expression';
    const CONDITIONS_SERIALIZED = 'conditions_serialized';
    const ACTIONS_SERIALIZED = 'actions_serialized';
    const SORT_ORDER = 'sort_order';
    const COMMENTS   = 'comments';
    const STORE_IDS  = 'store_ids';

    //alias for id
    const ID_ALIAS = 'id';

    //model
    const MODEL = 'canonical_rewrite_model';

    //rule
    const RULE_FORM_NAME = 'seo_canonical_rewrite_form';
    const RULE_FIELDSET_NAME = 'rule_conditions_fieldset';

    /**
     * @return int|null
     */
    public function getId();

    /**
     * @return int|null
     */
    public function getIsActive();

    /**
     * @param int|null $value
     * @return $this
     */
    public function setIsActive($value);

    /**
     * @return string|null
     */
    public function getCanonical();

    /**
     * @param string|null $value
     * @return $this
     */
    public function setCanonical($value);

    /**
     * @return string|null
     */
    public function getRegExpression();

    /**
     * @param string|null $value
     * @return $this
     */
    public function setRegExpression($value);

    /**
     * @return string|null
     */
    public function getConditionsSerialized();

    /**
     * @param string|null $value
     * @return $this
     */
    public function setConditionsSerialized($value);

    /**
     * @return string|null
     */
    public function getActionsSerialized();

    /**
     * @param string|null $value
     * @return $this
     */
    public function setActionsSerialized($value);

    /**
     * @return int|null
     */
    public function getSortOrder();

    /**
     * @param int|null $value
     * @return $this
     */
    public function setSortOrder($value);

    /**
     * @return string|null
     */
    public function getComments();

    /**
     * @param string|null $value
     * @return $this
     */
    public function setComments($value);

    /**
     * @return int[]
     */
    public function getStoreIds(): array;

    /**
     * @param int[] $value
     * @return $this
     */
    public function setStoreIds(array $value): self;
}
