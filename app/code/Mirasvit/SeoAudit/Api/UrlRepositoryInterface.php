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

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Mirasvit\SeoAudit\Api\Data\UrlInterface;
use Mirasvit\SeoAudit\Api\Data\UrlSearchResultsInterface;

/**
 * @api
 */
interface UrlRepositoryInterface
{
    /**
     * @param int $urlId
     * @return \Mirasvit\SeoAudit\Api\Data\UrlInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $urlId): UrlInterface;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Mirasvit\SeoAudit\Api\Data\UrlSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): UrlSearchResultsInterface;
}
