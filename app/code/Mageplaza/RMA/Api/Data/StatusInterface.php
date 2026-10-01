<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Api\Data;

/**
 * Interface StatusInterface
 * @package Mageplaza\RMA\Api\Data
 */
interface StatusInterface
{
    const STATUS_ID        = 'status_id';
    const NAME             = 'name';
    const LABEL            = 'label';
    const COMMENT          = 'comment';
    const ENABLE_COMMENT   = 'enable_comment';
    const IS_ACTIVE        = 'is_active';
    const DESCRIPTION      = 'description';
    const ALLOW_ACTION     = 'allow_action';
    const UPDATED_AT       = 'updated_at';
    const CREATED_AT       = 'created_at';
    const LABEL_BY_STORE   = 'label_by_store';
    const COMMENT_BY_STORE = 'comment_by_store';

    /**
     * @return int
     */
    public function getStatusId();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setStatusId($value);

    /**
     * @return string
     */
    public function getName();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setName($value);

    /**
     * @return string
     */
    public function getLabel();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setLabel($value);

    /**
     * @return string
     */
    public function getComment();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setComment($value);

    /**
     * @return int
     */
    public function getEnableComment();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setEnableComment($value);

    /**
     * @return int
     */
    public function getIsActive();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setIsActive($value);

    /**
     * @return string
     */
    public function getDescription();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setDescription($value);

    /**
     * @return string
     */
    public function getAllowAction();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setAllowAction($value);

    /**
     * @return string
     */
    public function getUpdatedAt();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setUpdatedAt($value);

    /**
     * @return string
     */
    public function getCreatedAt();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setCreatedAt($value);

    /**
     * @return \Mageplaza\RMA\Api\Data\StatusStoreLabelInterface[]
     */
    public function getLabelByStore();

    /**
     * @param \Mageplaza\RMA\Api\Data\StatusStoreLabelInterface[] $value
     *
     * @return $this
     */
    public function setLabelByStore($value);

    /**
     * @return \Mageplaza\RMA\Api\Data\StatusStoreCommentInterface[]
     */
    public function getCommentByStore();

    /**
     * @param \Mageplaza\RMA\Api\Data\StatusStoreCommentInterface[] $value
     *
     * @return $this
     */
    public function setCommentByStore($value);
}
