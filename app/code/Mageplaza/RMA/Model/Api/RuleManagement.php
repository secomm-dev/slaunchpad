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

namespace Mageplaza\RMA\Model\Api;

use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\InputException;
use Magento\Sales\Model\Order\ItemFactory as OrderItemFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\RMA\Api\Data\ItemAdditionalFieldInterface;
use Mageplaza\RMA\Api\Data\ReasonInterface;
use Mageplaza\RMA\Api\Data\RuleInterface;
use Mageplaza\RMA\Api\Data\SolutionInterface;
use Mageplaza\RMA\Api\RuleManagementInterface;
use Mageplaza\RMA\Api\SearchResult\RuleSearchResultInterface;
use Mageplaza\RMA\Api\SearchResult\RuleSearchResultInterfaceFactory;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\ResourceModel\Rule as RuleResource;
use Mageplaza\RMA\Model\ResourceModel\Rule\Collection;
use Mageplaza\RMA\Model\ResourceModel\Rule\CollectionFactory;
use Mageplaza\RMA\Model\Rule;
use Mageplaza\RMA\Model\RuleFactory;

/**
 * Class RuleManagement
 * @package Mageplaza\RMA\Model\Api
 */
class RuleManagement extends AbstractManagement implements RuleManagementInterface
{
    /**
     * @var RuleFactory
     */
    protected $ruleFactory;

    /**
     * @var RuleSearchResultInterfaceFactory
     */
    protected $ruleSearchResultFactory;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var RuleResource
     */
    protected $ruleResource;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * RuleManagement constructor.
     *
     * @param HelperData $helperData
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CollectionProcessorInterface $collectionProcessor
     * @param StoreManagerInterface $storeManager
     * @param OrderItemFactory $orderItemFactory
     * @param OrderFactory $orderFactory
     * @param RuleFactory $ruleFactory
     * @param RuleSearchResultInterfaceFactory $ruleSearchResultFactory
     * @param CollectionFactory $collectionFactory
     * @param RuleResource $ruleResource
     */
    public function __construct(
        HelperData $helperData,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CollectionProcessorInterface $collectionProcessor,
        StoreManagerInterface $storeManager,
        OrderItemFactory $orderItemFactory,
        OrderFactory $orderFactory,
        RuleFactory $ruleFactory,
        RuleSearchResultInterfaceFactory $ruleSearchResultFactory,
        CollectionFactory $collectionFactory,
        RuleResource $ruleResource
    ) {
        $this->ruleFactory             = $ruleFactory;
        $this->ruleSearchResultFactory = $ruleSearchResultFactory;
        $this->collectionFactory       = $collectionFactory;
        $this->ruleResource            = $ruleResource;
        $this->storeManager            = $storeManager;

        parent::__construct(
            $helperData,
            $searchCriteriaBuilder,
            $collectionProcessor,
            $orderItemFactory,
            $orderFactory
        );
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        return $this->deleteEntity($this->ruleFactory->create(), $id, $this->ruleResource);
    }

    /**
     * @inheritdoc
     */
    public function save(RuleInterface $entity)
    {
        $this->checkEnabled();
        $this->checkRequired([Rule::NAME], $entity->getData());
        $ruleModel = $this->ruleFactory->create();
        $helper = $this->helperData;

        if ($entity->getStatus()) {
            $helper->checkYesNo($entity->getStatus(), Rule::STATUS);
        } else {
            $entity->setStatus(1);
        }

        if ($entity->getWebsites()) {
            $helper->checkWebsites($entity->getWebsites());
        } else {
            $entity->setWebsites($this->storeManager->getWebsite()->getId());
        }

        if ($entity->getCustomerGroup()) {
            $helper->checkCustomerGroup($entity->getCustomerGroup());
        } else {
            $entity->setCustomerGroup(0);
        }

        if ($entity->getReason()) {
            $reason = $this->processInformation($entity->getReason(), $helper->getReasonOptionArray(), Rule::REASON);
            $entity->setReason($reason);
        }

        if ($entity->getSolution()) {
            $solution = $this->processInformation(
                $entity->getSolution(),
                $helper->getSolutionOptionArray(),
                Rule::SOLUTION
            );
            $entity->setSolution($solution);
        }

        if ($entity->getAdditionalField()) {
            $additional = $this->processInformation(
                $entity->getAdditionalField(),
                $helper->getAdditionalFieldOptionArray(),
                Rule::ADDITIONAL_FIELD
            );
            $entity->setAdditionalField($additional);
        }

        if ($entity->getPriority()) {
            $helper->checkIsInt($entity->getPriority());
        }

        return $this->saveEntity($entity, $ruleModel, 'rule', $this->ruleResource);
    }

    /**
     * @inheritdoc
     */
    public function getList(?SearchCriteriaInterface $searchCriteria = null)
    {
        /** @var RuleSearchResultInterface $searchResult */
        $searchResult = $this->ruleSearchResultFactory->create();

        /** @var Collection $ruleCollection */
        $ruleCollection = $this->collectionFactory->create();

        return $this->getListEntity($searchCriteria, $searchResult, $ruleCollection);
    }

    /**
     * @param ReasonInterface[]|SolutionInterface[]|ItemAdditionalFieldInterface[] $data
     * @param array $options
     * @param string $type
     *
     * @return string
     * @throws InputException
     */
    public function processInformation($data, $options, $type)
    {
        $result = [];

        foreach ($data as $item) {
            $this->helperData->checkRuleInfo($item->getValue(), $options, $type);
            $result[] = $item->getValue();
        }

        return implode(',', $result);
    }
}
