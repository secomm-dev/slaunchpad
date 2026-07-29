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

namespace Mirasvit\SeoContent\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Mirasvit\SeoContent\Api\Data\RewriteInterface;
use Mirasvit\SeoContent\Api\Data\RewriteSearchResultsInterface;

/**
 * @api
 */
interface RewriteRepositoryInterface
{
    /**
     * @param int $rewriteId
     * @return \Mirasvit\SeoContent\Api\Data\RewriteInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $rewriteId): RewriteInterface;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Mirasvit\SeoContent\Api\Data\RewriteSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): RewriteSearchResultsInterface;

    /**
     * @param \Mirasvit\SeoContent\Api\Data\RewriteInterface $rewrite
     * @param int|null $rewriteId
     * @return \Mirasvit\SeoContent\Api\Data\RewriteInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(RewriteInterface $rewrite, ?int $rewriteId = null): RewriteInterface;

    /**
     * @param int $rewriteId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $rewriteId): bool;
}
