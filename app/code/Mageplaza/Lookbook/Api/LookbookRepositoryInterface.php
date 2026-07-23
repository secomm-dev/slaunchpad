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
 * @package     Mageplaza_Lookbook
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Lookbook\Api;

/**
 * Interface LookbookRepositoryInterface
 * @package Mageplaza\Lookbook\Api
 */
interface LookbookRepositoryInterface
{
    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface|null $searchCriteria
     *
     * @return \Mageplaza\Lookbook\Api\Data\LookbookSearchResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(?\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria = null);
}
