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
use Magento\Sales\Model\Order\ItemFactory as OrderItemFactory;
use Magento\Sales\Model\OrderFactory;
use Mageplaza\RMA\Api\Data\StatusInterface;
use Mageplaza\RMA\Api\SearchResult\StatusSearchResultInterface;
use Mageplaza\RMA\Api\SearchResult\StatusSearchResultInterfaceFactory;
use Mageplaza\RMA\Api\StatusManagementInterface;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\Config\Source\RMAStatus\Action;
use Mageplaza\RMA\Model\ResourceModel\Status as StatusResource;
use Mageplaza\RMA\Model\ResourceModel\Status\Collection;
use Mageplaza\RMA\Model\ResourceModel\Status\CollectionFactory;
use Mageplaza\RMA\Model\Status;
use Mageplaza\RMA\Model\StatusFactory;

/**
 * Class StatusManagement
 * @package Mageplaza\RMA\Model\Api
 */
class StatusManagement extends AbstractManagement implements StatusManagementInterface
{
    /**
     * @var StatusFactory
     */
    protected $statusFactory;

    /**
     * @var StatusSearchResultInterfaceFactory
     */
    protected $statusSearchResultFactory;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var StatusResource
     */
    protected $statusResource;

    /**
     * @var Action
     */
    protected $action;

    /**
     * StatusManagement constructor.
     *
     * @param HelperData $helperData
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CollectionProcessorInterface $collectionProcessor
     * @param OrderItemFactory $orderItemFactory
     * @param OrderFactory $orderFactory
     * @param StatusFactory $statusFactory
     * @param StatusSearchResultInterfaceFactory $statusSearchResultFactory
     * @param CollectionFactory $collectionFactory
     * @param StatusResource $statusResource
     * @param Action $action
     */
    public function __construct(
        HelperData $helperData,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CollectionProcessorInterface $collectionProcessor,
        OrderItemFactory $orderItemFactory,
        OrderFactory $orderFactory,
        StatusFactory $statusFactory,
        StatusSearchResultInterfaceFactory $statusSearchResultFactory,
        CollectionFactory $collectionFactory,
        StatusResource $statusResource,
        Action $action
    ) {
        $this->statusFactory             = $statusFactory;
        $this->statusSearchResultFactory = $statusSearchResultFactory;
        $this->collectionFactory         = $collectionFactory;
        $this->statusResource            = $statusResource;
        $this->action                    = $action;
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
        return $this->deleteEntity($this->statusFactory->create(), $id, $this->statusResource);
    }

    /**
     * @inheritdoc
     */
    public function save(StatusInterface $entity)
    {
        $this->checkEnabled();
        $this->checkRequired([Status::NAME, Status::LABEL, Status::COMMENT], $entity->getData());
        $statusModel = $this->statusFactory->create();
        $helper = $this->helperData;

        if ($entity->getIsActive()) {
            $helper->checkYesNo($entity->getIsActive(), Status::IS_ACTIVE);
        } else {
            $entity->setIsActive(1);
        }

        if ($entity->getAllowAction()) {
            $helper->checkConfigSource($entity->getAllowAction(), $this->action->toArray(), Status::ALLOW_ACTION);
        }

        if ($entity->getEnableComment()) {
            $helper->checkYesNo($entity->getEnableComment(), Status::ENABLE_COMMENT);
        } else {
            $entity->setEnableComment(0);
        }

        if ($entity->getLabelByStore()) {
            $this->helperData->checkStoreChildItem($entity->getLabelByStore());
        }

        if ($entity->getCommentByStore()) {
            $this->helperData->checkStoreChildItem($entity->getCommentByStore());
        }

        return $this->saveEntity(
            $entity,
            $statusModel,
            'status',
            $this->statusResource
        );
    }

    /**
     * @inheritdoc
     */
    public function getList(?SearchCriteriaInterface $searchCriteria = null)
    {
        /** @var StatusSearchResultInterface $searchResult */
        $searchResult = $this->statusSearchResultFactory->create();

        /** @var Collection $ruleCollection */
        $ruleCollection = $this->collectionFactory->create();

        return $this->getListEntity($searchCriteria, $searchResult, $ruleCollection);
    }
}
