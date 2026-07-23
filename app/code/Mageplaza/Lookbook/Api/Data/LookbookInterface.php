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
 * @package     Mageplaza_Lookbook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Lookbook\Api\Data;

/**
 * Interface LookbookInterface
 * @package Mageplaza\Lookbook\Api\Data
 */
interface LookbookInterface
{
    const LOOKBOOK_ID = 'lookbook_id';
    const NAME        = 'name';
    const STATUS      = 'status';
    const STORE_IDS   = 'store_ids';
    const MARKER_TYPE = 'marker_type';
    const IMAGE       = 'image';
    const MARKER      = 'marker';
    const WIDTH       = 'width';
    const HEIGHT      = 'height';
    const CREATED_AT  = 'created_at';
    const UPDATED_AT  = 'updated_at';

    /**
     * @return int
     */
    public function getLookbookId();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setLookbookId($value);
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
     * @return int
     */
    public function getStatus();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setStatus($value);

    /**
     * @return string
     */
    public function getStoreIds();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setStoreIds($value);

    /**
     * @return int
     */
    public function getMarkerType();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setMarkerType($value);

    /**
     * @return string
     */
    public function getImage();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setImage($value);

    /**
     * @return string
     */
    public function getMarker();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setMarker($value);

    /**
     * @return string
     */
    public function getWidth();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setWidth($value);

    /**
     * @return string
     */
    public function getHeight();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setHeight($value);

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
     * @return string
     */
    public function getUpdatedAt();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setUpdatedAt($value);
}
