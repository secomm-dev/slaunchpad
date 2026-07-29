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

namespace Mirasvit\SeoAutolink\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Mirasvit\SeoAutolink\Api\Data\LinkInterface;
use Mirasvit\SeoAutolink\Api\Data\LinkSearchResultsInterface;

/**
 * @api
 */
interface LinkRepositoryInterface
{
    /**
     * Get link by ID.
     *
     * @param int $linkId
     * @return \Mirasvit\SeoAutolink\Api\Data\LinkInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $linkId): LinkInterface;

    /**
     * Get list of links matching the given search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Mirasvit\SeoAutolink\Api\Data\LinkSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): LinkSearchResultsInterface;

    /**
     * Save link. If linkId is provided, performs a load-then-merge update; otherwise creates a new record.
     *
     * @param \Mirasvit\SeoAutolink\Api\Data\LinkInterface $link
     * @param int|null $linkId
     * @return \Mirasvit\SeoAutolink\Api\Data\LinkInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(LinkInterface $link, ?int $linkId = null): LinkInterface;

    /**
     * Delete link by ID.
     *
     * @param int $linkId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $linkId): bool;
}
