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

namespace Mirasvit\SeoMarkup\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Mirasvit\SeoMarkup\Api\Data\ExtenderInterface;
use Mirasvit\SeoMarkup\Api\Data\ExtenderSearchResultsInterface;

/**
 * @api
 */
interface ExtenderRepositoryInterface
{
    /**
     * @param int $extenderId
     * @return \Mirasvit\SeoMarkup\Api\Data\ExtenderInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $extenderId): ExtenderInterface;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Mirasvit\SeoMarkup\Api\Data\ExtenderSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): ExtenderSearchResultsInterface;

    /**
     * @param \Mirasvit\SeoMarkup\Api\Data\ExtenderInterface $extender
     * @param int|null $extenderId
     * @return \Mirasvit\SeoMarkup\Api\Data\ExtenderInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(ExtenderInterface $extender, ?int $extenderId = null): ExtenderInterface;

    /**
     * @param int $extenderId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $extenderId): bool;
}
