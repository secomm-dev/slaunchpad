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
 * Interface ShippingLabelLabelInterface
 * @package Mageplaza\RMA\Api\Data
 */
interface ShippingStoreLabelsInterface
{
    const STORE_ID = 'store_id';
    const LABEL    = 'label';

    /**
     * @return int
     */
    public function getStoreId();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setStoreId($value);

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
}
