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
 * @package     Mageplaza_ThankYouPage
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ThankYouPage\Api;

/**
 * Interface ThankYouPageRepositoryInterface
 * @package Mageplaza\ThankYouPage\Api
 */
interface ThankYouPageRepositoryInterface
{
    /**
     * Get all rules
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface|null $searchCriteria
     *
     * @return \Mageplaza\ThankYouPage\Api\Data\ThankYouPageSearchResultInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getList(?\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria = null);

    /**
     * Get rule by ID.
     *
     * @param int $ruleId
     * @return \Mageplaza\ThankYouPage\Api\Data\ThankYouPageInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($ruleId);

    /**
     * Get order success page by orderId
     *
     * @param string $orderId
     *
     * @return \Mageplaza\ThankYouPage\Api\Data\ThankYouPageInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Exception
     */
    public function getOrderPage($orderId);

    /**
     * Get subscribe page by email
     *
     * @param string $email
     * @param int|null $storeId
     * @param int|null $customerGroup
     *
     * @return \Mageplaza\ThankYouPage\Api\Data\ThankYouPageInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Exception
     */
    public function getSubsPage($email, $storeId = null, $customerGroup = null);

    /**
     * Save sales rule.
     *
     * @param \Mageplaza\ThankYouPage\Api\Data\ThankYouPageInterface $template
     * @return \Mageplaza\ThankYouPage\Api\Data\ThankYouPageInterface
     * @throws \Magento\Framework\Exception\InputException If there is a problem with the input
     * @throws \Magento\Framework\Exception\NoSuchEntityException If a rule ID is sent but the rule does not exist
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(\Mageplaza\ThankYouPage\Api\Data\ThankYouPageInterface $template);

    /**
     * delete rule by ID.
     *
     * @param int $ruleId
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function delete($ruleId);
}
