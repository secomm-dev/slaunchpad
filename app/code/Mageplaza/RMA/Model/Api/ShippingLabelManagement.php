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
use Mageplaza\RMA\Api\Data\ShippingLabelInterface;
use Mageplaza\RMA\Api\SearchResult\ShippingLabelSearchResultInterface;
use Mageplaza\RMA\Api\SearchResult\ShippingLabelSearchResultInterfaceFactory;
use Mageplaza\RMA\Api\ShippingLabelManagementInterface;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\Config\Source\RMAShippingLabel\BarcodeType;
use Mageplaza\RMA\Model\Config\Source\RMAShippingLabel\Information;
use Mageplaza\RMA\Model\ResourceModel\ShippingLabel as ShippingLabelResource;
use Mageplaza\RMA\Model\ResourceModel\ShippingLabel\Collection;
use Mageplaza\RMA\Model\ResourceModel\ShippingLabel\CollectionFactory;
use Mageplaza\RMA\Model\ShippingLabel;
use Mageplaza\RMA\Model\ShippingLabelFactory;

/**
 * Class ShippingLabelManagement
 * @package Mageplaza\RMA\Model\Api
 */
class ShippingLabelManagement extends AbstractManagement implements ShippingLabelManagementInterface
{
    /**
     * @var ShippingLabelFactory
     */
    protected $shippingLabelFactory;

    /**
     * @var ShippingLabelSearchResultInterfaceFactory
     */
    protected $shippingLabelSearchResultFactory;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var ShippingLabelResource
     */
    protected $shippingLabelResource;

    /**
     * @var BarcodeType
     */
    protected $barcodeType;

    /**
     * @var Information
     */
    protected $information;

    /**
     * ShippingLabelManagement constructor.
     *
     * @param HelperData $helperData
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CollectionProcessorInterface $collectionProcessor
     * @param OrderItemFactory $orderItemFactory
     * @param OrderFactory $orderFactory
     * @param ShippingLabelFactory $shippingLabelFactory
     * @param ShippingLabelSearchResultInterfaceFactory $shippingLabelSearchResultFactory
     * @param CollectionFactory $collectionFactory
     * @param ShippingLabelResource $shippingLabelResource
     * @param BarcodeType $barcodeType
     * @param Information $information
     */
    public function __construct(
        HelperData $helperData,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CollectionProcessorInterface $collectionProcessor,
        OrderItemFactory $orderItemFactory,
        OrderFactory $orderFactory,
        ShippingLabelFactory $shippingLabelFactory,
        ShippingLabelSearchResultInterfaceFactory $shippingLabelSearchResultFactory,
        CollectionFactory $collectionFactory,
        ShippingLabelResource $shippingLabelResource,
        BarcodeType $barcodeType,
        Information $information
    ) {
        $this->shippingLabelFactory             = $shippingLabelFactory;
        $this->shippingLabelSearchResultFactory = $shippingLabelSearchResultFactory;
        $this->collectionFactory                = $collectionFactory;
        $this->shippingLabelResource            = $shippingLabelResource;
        $this->barcodeType                      = $barcodeType;
        $this->information                      = $information;

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
        return $this->deleteEntity($this->shippingLabelFactory->create(), $id, $this->shippingLabelResource);
    }

    /**
     * @inheritdoc
     */
    public function save(ShippingLabelInterface $entity)
    {
        $this->checkEnabled();
        $this->checkRequired(
            [ShippingLabel::NAME, ShippingLabel::RETURN_ADDRESS, ShippingLabel::LABEL],
            $entity->getData()
        );
        $shippingLabelModel = $this->shippingLabelFactory->create();
        $helper = $this->helperData;

        if ($entity->getStatus()) {
            $helper->checkYesNo($entity->getStatus(), ShippingLabel::STATUS);
        } else {
            $entity->setStatus(1);
        }

        if ($entity->getStoreId()) {
            $helper->checkStore($entity->getStoreId());
        } else {
            $entity->setStoreId(0);
        }

        if ($entity->getShippingLabelByStore()) {
            $this->helperData->checkStoreChildItem($entity->getShippingLabelByStore());
        }

        if ($entity->getBarcode()) {
            $helper->checkConfigSource($entity->getBarcode(), $this->barcodeType->toArray(), ShippingLabel::BARCODE);
        } else {
            $entity->setBarcode(1);
        }

        if ($entity->getInformation()) {
            $helper->checkConfigSource(
                $entity->getInformation(),
                $this->information->toArray(),
                ShippingLabel::INFORMATION
            );
        }

        if ($entity->getPriority()) {
            $helper->checkIsInt($entity->getPriority());
        }

        $entity->setImage($this->helperData->uploadFile($entity->getUpload()));

        return $this->saveEntity(
            $entity,
            $shippingLabelModel,
            'shipping_label',
            $this->shippingLabelResource
        );
    }

    /**
     * @inheritdoc
     */
    public function getList(?SearchCriteriaInterface $searchCriteria = null)
    {
        /** @var ShippingLabelSearchResultInterface $searchResult */
        $searchResult = $this->shippingLabelSearchResultFactory->create();

        /** @var Collection $ruleCollection */
        $ruleCollection = $this->collectionFactory->create();

        return $this->getListEntity($searchCriteria, $searchResult, $ruleCollection);
    }
}
