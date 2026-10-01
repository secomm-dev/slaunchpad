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
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Api;

use Exception;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Interface StatusManagementInterface
 * @package Mageplaza\RMA\Api
 */
interface StatusManagementInterface
{
    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     *
     * @return \Mageplaza\RMA\Api\SearchResult\StatusSearchResultInterface
     */
    public function getList(?\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria = null);

    /**
     * @param string $id
     *
     * @return bool
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function delete($id);

    /**
     * @param \Mageplaza\RMA\Api\Data\StatusInterface $status
     *
     * @return \Mageplaza\RMA\Api\Data\StatusInterface
     * @throws Exception
     */
    public function save(\Mageplaza\RMA\Api\Data\StatusInterface $status);
}
