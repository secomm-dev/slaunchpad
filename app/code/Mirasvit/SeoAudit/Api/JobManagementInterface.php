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

namespace Mirasvit\SeoAudit\Api;

/**
 * @api
 */
interface JobManagementInterface
{
    /**
     * @return \Mirasvit\SeoAudit\Api\Data\JobInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function startJob(): \Mirasvit\SeoAudit\Api\Data\JobInterface;

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function runJob(): bool;

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function reset(): bool;
}
