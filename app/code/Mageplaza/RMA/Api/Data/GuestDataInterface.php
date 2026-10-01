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
 * Interface GuestDataInterface
 * @package Mageplaza\RMA\Api\Data
 */
interface GuestDataInterface
{
    const BILLING_LAST_NAME = 'billing_last_name';
    const FIND_BY           = 'find_by';
    const EMAIL             = 'email';
    const ZIP_CODE          = 'zip_code';

    /**
     * @return string
     */
    public function getBillingLastName();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setBillingLastName($value);

    /**
     * @return string
     */
    public function getFindBy();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setFindBy($value);

    /**
     * @return string
     */
    public function getEmail();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setEmail($value);

    /**
     * @return string
     */
    public function getZipCode();

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setZipCode($value);
}
