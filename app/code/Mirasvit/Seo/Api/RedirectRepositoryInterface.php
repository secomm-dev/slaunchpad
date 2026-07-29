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

namespace Mirasvit\Seo\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Mirasvit\Seo\Api\Data\RedirectInterface;
use Mirasvit\Seo\Api\Data\RedirectSearchResultsInterface;

/**
 * @api
 */
interface RedirectRepositoryInterface
{
    /**
     * Get redirect by ID.
     *
     * @param int $redirectId
     * @return \Mirasvit\Seo\Api\Data\RedirectInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $redirectId): RedirectInterface;

    /**
     * Get list of redirects matching the given search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Mirasvit\Seo\Api\Data\RedirectSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): RedirectSearchResultsInterface;

    /**
     * Save redirect. If redirectId is provided, performs a load-then-merge update; otherwise creates a new record.
     *
     * @param \Mirasvit\Seo\Api\Data\RedirectInterface $redirect
     * @param int|null $redirectId
     * @return \Mirasvit\Seo\Api\Data\RedirectInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(RedirectInterface $redirect, ?int $redirectId = null): RedirectInterface;

    /**
     * Delete redirect by ID.
     *
     * @param int $redirectId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $redirectId): bool;
}
