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
 * Interface RequestShippingLabelInterface
 * @package Mageplaza\RMA\Api\Data
 */
interface RequestShippingLabelInterface
{
    const SHIPPING_LABEL_ID = 'shipping_label_id';
    const REQUEST_ID        = 'request_id';

    /**
     * @return int
     */
    public function getShippingLabelId();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setShippingLabelId($value);

    /**
     * @return int
     */
    public function getRequestId();

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setRequestId($value);
}
