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
 * Interface RequestManagementInterface
 * @package Mageplaza\RMA\Api
 */
interface RequestManagementInterface
{
    /**
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     *
     * @return \Mageplaza\RMA\Api\SearchResult\RequestSearchResultInterface
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
     * @param \Mageplaza\RMA\Api\Data\RequestInterface $request
     *
     * @return \Mageplaza\RMA\Api\Data\RequestInterface
     * @throws Exception
     */
    public function save(\Mageplaza\RMA\Api\Data\RequestInterface $request);

    /**
     * @param string $customerId
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     *
     * @return \Mageplaza\RMA\Api\SearchResult\RequestSearchResultInterface
     */
    public function getMine($customerId, ?\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria = null);

    /**
     * @param \Mageplaza\RMA\Api\Data\RequestReplyInterface $reply
     *
     * @return \Mageplaza\RMA\Api\Data\RequestReplyInterface
     * @throws Exception
     */
    public function saveAdminReply(\Mageplaza\RMA\Api\Data\RequestReplyInterface $reply);

    /**
     * @param string $customerId
     * @param \Mageplaza\RMA\Api\Data\RequestReplyInterface $reply
     *
     * @return \Mageplaza\RMA\Api\Data\RequestReplyInterface
     * @throws Exception
     */
    public function saveCustomerReply($customerId, \Mageplaza\RMA\Api\Data\RequestReplyInterface $reply);

    /**
     * @param string $customerId
     * @param \Mageplaza\RMA\Api\Data\RequestReplyInterface $cancel
     *
     * @return \Mageplaza\RMA\Api\Data\RequestReplyInterface
     * @throws Exception
     */
    public function cancel($customerId, \Mageplaza\RMA\Api\Data\RequestReplyInterface $cancel);

    /**
     * @param \Mageplaza\RMA\Api\Data\RequestInterface $request
     *
     * @return \Mageplaza\RMA\Api\Data\RequestInterface
     * @throws Exception
     */
    public function saveMine(\Mageplaza\RMA\Api\Data\RequestInterface $request);

    /**
     * @param \Mageplaza\RMA\Api\Data\RequestInterface $request
     *
     * @return \Mageplaza\RMA\Api\Data\RequestInterface
     * @throws Exception
     */
    public function saveGuest(\Mageplaza\RMA\Api\Data\RequestInterface $request);
}
