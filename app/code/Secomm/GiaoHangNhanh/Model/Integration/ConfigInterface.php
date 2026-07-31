<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Secomm Integration
 */
namespace Secomm\GiaoHangNhanh\Model\Integration;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Interface ConfigInterface
 * @package Secomm\GiaoHangNhanh\Model\Integration
 */
interface ConfigInterface
{
    /**
     * Retrieve information from integration configuration
     *
     * @param string $field
     * @param int|null $storeId
     *
     * @return mixed
     */
    public function getValue($field, $storeId = null);

    /**
     * Retrieve config flag by path and scope
     * @param $field
     * @param null $storeId
     * @return bool
     */
    public function isSetFlag($field, $storeId = null);

    /**
     * Sets path pattern
     *
     * @param string $pathPattern
     * @return void
     */
    public function setPathPattern($pathPattern);

    /**
     * Sets integration code
     *
     * @param string $integrationType
     * @return void
     */
    public function setIntegrationType($integrationType);
}
