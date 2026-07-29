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
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Mirasvit\SeoAudit\Api\Data\JobInterface;
use Mirasvit\SeoAudit\Api\Data\JobSearchResultsInterface;

/**
 * @api
 */
interface JobRepositoryInterface
{
    /**
     * @param int $jobId
     * @return \Mirasvit\SeoAudit\Api\Data\JobInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $jobId): JobInterface;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Mirasvit\SeoAudit\Api\Data\JobSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): JobSearchResultsInterface;

    /**
     * @param \Mirasvit\SeoAudit\Api\Data\JobInterface $job
     * @param int|null $jobId
     * @return \Mirasvit\SeoAudit\Api\Data\JobInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(JobInterface $job, ?int $jobId = null): JobInterface;

    /**
     * @param int $jobId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $jobId): bool;
}
