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

namespace Mirasvit\SeoSitemap\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Mirasvit\SeoSitemap\Api\Data\ProviderInterface;
use Mirasvit\SeoSitemap\Api\Data\ProviderSearchResultsInterface;

/**
 * @api
 */
interface ProviderRepositoryInterface
{
    /**
     * @param int $providerId
     * @return \Mirasvit\SeoSitemap\Api\Data\ProviderInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $providerId): ProviderInterface;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Mirasvit\SeoSitemap\Api\Data\ProviderSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): ProviderSearchResultsInterface;

    /**
     * @param \Mirasvit\SeoSitemap\Api\Data\ProviderInterface $provider
     * @param int|null $providerId
     * @return \Mirasvit\SeoSitemap\Api\Data\ProviderInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(ProviderInterface $provider, ?int $providerId = null): ProviderInterface;

    /**
     * @param int $providerId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $providerId): bool;
}
