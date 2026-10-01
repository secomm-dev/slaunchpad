<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license sliderConfig is
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

use Exception;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\ItemFactory as OrderItemFactory;
use Magento\Sales\Model\OrderFactory;
use Mageplaza\RMA\Api\Data\ItemAdditionalFieldInterface;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Helper\Image;
use Mageplaza\RMA\Model\Request;
use Mageplaza\RMA\Model\Request\Item;
use Mageplaza\RMA\Model\Request\Reply;
use Mageplaza\RMA\Model\ResourceModel\Request\Collection as RequestCollection;
use Mageplaza\RMA\Model\ResourceModel\Rule\Collection as RuleCollection;
use Mageplaza\RMA\Model\ResourceModel\ShippingLabel\Collection as ShippingLabelCollection;
use Mageplaza\RMA\Model\Rule;
use Mageplaza\RMA\Model\ShippingLabel;
use Mageplaza\RMA\Model\Status;

/**
 * Class AbstractManagement
 * @package Mageplaza\RMA\Model\Api
 */
class AbstractManagement
{
    /**
     * @var HelperData
     */
    protected $helperData;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var CollectionProcessorInterface
     */
    protected $collectionProcessor;

    /**
     * @var OrderItemFactory
     */
    protected $orderItemFactory;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * AbstractManagement constructor.
     *
     * @param HelperData $helperData
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CollectionProcessorInterface $collectionProcessor
     * @param OrderItemFactory $orderItemFactory
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        HelperData $helperData,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CollectionProcessorInterface $collectionProcessor,
        OrderItemFactory $orderItemFactory,
        OrderFactory $orderFactory
    ) {
        $this->helperData = $helperData;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->collectionProcessor = $collectionProcessor;
        $this->orderItemFactory = $orderItemFactory;
        $this->orderFactory = $orderFactory;
    }

    /**
     * @inheritdoc
     *
     * @throws LocalizedException
     */
    public function getListEntity($searchCriteria, $searchResult, $collection)
    {
        $this->checkEnabled();

        if ($searchCriteria === null) {
            $searchCriteria = $this->searchCriteriaBuilder->create();
        } else {
            $this->collectionProcessor->process($searchCriteria, $collection);
        }

        if ($collection instanceof RuleCollection) {
            foreach ($collection->getItems() as $rule) {
                $rule->setReason($this->getDataInformation($rule->getReason(), 'rma/reason'));
                $rule->setSolution($this->getDataInformation($rule->getSolution(), 'rma/solution'));
                $rule->setAdditionalField(
                    $this->getDataInformation($rule->getAdditionalField(), 'rma/additional_field')
                );
            }
        }

        if ($collection instanceof ShippingLabelCollection) {
            foreach ($collection->getItems() as $item) {
                if ($item->getImage()) {
                    $item->setImage($this->helperData->getFileUrl(
                        $item->getImage(),
                        Image::TEMPLATE_MEDIA_TYPE_SHIPPING_LABEL
                    ));
                }
            }
        }

        if ($collection instanceof RequestCollection) {
            foreach ($collection->getItems() as $item) {
                if ($item->getFiles()) {
                    $item->setFiles($this->processFileUrl($item->getFiles()));
                }

                if ($item->getRequestReply()) {
                    foreach ($item->getRequestReply() as $reply) {
                        if ($reply->getFiles()) {
                            $reply->setFiles($this->processFileUrl($reply->getFiles()));
                        }
                    }
                }
            }
        }

        $searchResult->setItems($collection->getItems());
        $searchResult->setTotalCount($collection->getSize());
        $searchResult->setSearchCriteria($searchCriteria);

        return $searchResult;
    }

    /**
     * @param string $files
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function processFileUrl($files)
    {
        $newFiles = [];
        $fileData = HelperData::jsonDecode($files);

        foreach ($fileData as $file) {
            $file['file'] = $this->helperData->getFileUrl($file['file']);
            $newFiles[] = $file['file'];
        }

        return $newFiles;
    }

    /**
     * @param Request|Rule|ShippingLabel|Status $entity
     * @param string $id
     * @param null $resource
     *
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws StateException
     */
    public function deleteEntity($entity, $id, $resource = null)
    {
        $this->checkEnabled();
        $entity->load($id);

        if (!$entity->getId()) {
            throw new
            NoSuchEntityException(__('The entity that was requested doesn\'t exist. Verify the entity and try again.'));
        }

        try {
            $resource->delete($entity);
        } catch (Exception $e) {
            throw new StateException(__('The entity can\'t be deleted. %1', $e->getMessage()));
        }

        return true;
    }

    /**
     * @throws LocalizedException
     */
    public function checkEnabled()
    {
        if (!$this->helperData->isEnabled()) {
            throw new LocalizedException(__('Module is disabled.'));
        }
    }

    /**
     * @inheritdoc
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws StateException
     */
    public function saveEntity($entity, $model, $type, $resource = null, $api = null)
    {
        if ($entity->getId()) {
            $model = $this->getEntityById($entity->getId(), $model);
        }

        $data = $this->processData($entity->getData(), $type, $api);
        $model->addData($data);

        try {
            $resource->save($model);
            $this->getEntityById($model->getId(), $model);
        } catch (Exception $e) {
            throw new StateException(__('The entity can\'t be saved. %1', $e->getMessage()));
        }

        if ($model instanceof ShippingLabel) {
            $model->setImage($this->helperData->getFileUrl(
                $model->getImage(),
                Image::TEMPLATE_MEDIA_TYPE_SHIPPING_LABEL
            ));
        }

        if ($model instanceof Request) {
            if ($model->getFiles()) {
                $model->setFiles($this->processFileUrl($model->getFiles()));
            }

            if ($model->getRequestReply()) {
                foreach ($model->getRequestReply() as $reply) {
                    if ($reply->getFiles()) {
                        $reply->setFiles($this->processFileUrl($reply->getFiles()));
                    }
                }
            }
        }

        if ($model instanceof Reply && $model->getFiles()) {
            $model->setFiles($this->processFileUrl($model->getFiles()));
        }

        return $model;
    }

    /**
     * @inheritdoc
     * @throws NoSuchEntityException
     */
    public function getEntityById($id, $model)
    {
        $model->load($id);

        if (!$model->getId()) {
            throw new NoSuchEntityException(
                __('The entity that was requested doesn\'t exist. Verify the rule and try again.')
            );
        }

        return $model;
    }

    /**
     * @param array $fields
     * @param array $data
     *
     * @throws ValidatorException
     */
    public function checkRequired($fields, $data)
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || !isset($data[$field])) {
                $missing[] = $field;
            }
        }

        if ($missing) {
            throw new ValidatorException(__('%1 is required.', implode(',', $missing)));
        }
    }

    /**
     * @param string $var
     *
     * @throws InputException
     */
    public function requiredGuestData($data, $var)
    {
        if (!$data) {
            throw new InputException(__('%1 is required.', $var));
        }
    }

    /**
     * @param array $data
     * @param string $type
     * @param string $api
     *
     * @return array
     * @throws InputException
     */
    public function processData($data, $type, $api)
    {
        switch ($type) {
            case 'shipping_label':
                $data = $this->filterStoreData($data, 'shipping_label_by_store', 'store_labels');
                break;
            case 'status':
                $data = $this->filterStoreData($data, 'label_by_store', 'store_labels');
                $data = $this->filterStoreData($data, 'comment_by_store', 'store_comments');
                break;
            case 'request':
                $data = $this->filterRequestData($data, $api);
                break;
        }

        return $data;
    }

    /**
     * @param string $customerId
     *
     * @return string
     */
    public function getCustomerName($customerId)
    {
        return $this->helperData->getCustomerName($customerId)->getName();
    }

    /**
     * @param array $data
     * @param string $field
     * @param string $modelField
     *
     * @return array
     */
    public function filterStoreData($data, $field, $modelField)
    {
        $store = [];
        if (isset($data[$field])) {
            foreach ($data[$field] as $item) {
                $store[$modelField][$item->getStoreId()] = $modelField === 'store_labels'
                    ? $item->getLabel()
                    : $item->getComment();
            }

            $data = array_merge($data, $store);
            unset($data[$field]);
        }

        return $data;
    }

    /**
     * @param array $data
     * @param string $api
     *
     * @return array
     * @throws InputException
     */
    public function filterRequestData($data, $api)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($data[Request::ORDER_INCREMENT_ID]);
        $helper = $this->helperData;

        if (!$order->getId()) {
            throw new InputException(__('%1 is invalid.', Request::ORDER_INCREMENT_ID));
        }

        $productData = [];

        if ($api === 'admin' || (isset($data[Request::REQUEST_ITEM]) && $helper->isReturnEachItem())) {
            foreach ($data[Request::REQUEST_ITEM] as $item) {
                if (!$item->getProductId()) {
                    throw new InputException(__('%1 is required.', Item::PRODUCT_ID));
                }

                if (!$item->getReason() && $helper->getValidatedReasons($item->getProductId(), $order)) {
                    throw new InputException(__('%1 is required.', Item::REASON));
                }

                if (!$item->getSolution() && $helper->getValidatedSolutions($item->getProductId(), $order)) {
                    throw new InputException(__('%1 is required.', Item::SOLUTION));
                }

                if (!$item->getAdditionalFields()
                    && $fields = $helper->getValidatedAdditionalFields($item->getProductId(), $order)) {
                    foreach ($fields as $field) {
                        if ($field['is_require']) {
                            throw new InputException(__('%1 is required.', Item::ADDITIONAL_FIELDS));
                        }
                    }
                }

                if (!$helper->canReturnProduct($item->getProductId(), $order)) {
                    throw new InputException(__('This product cannot return.'));
                }

                $orderItem = $this->orderItemFactory->create()
                    ->getCollection()
                    ->addFieldToFilter('order_id', $order->getId())
                    ->addFieldToFilter('product_id', $item->getProductId())->load()->getFirstItem();

                if (!$orderItem->getItemId()) {
                    throw new InputException(__('%1 is invalid.', Item::PRODUCT_ID));
                }

                $qtyRma = $item->getQtyRma();
                $qtyLeft = ((int)$orderItem->getQtyOrdered() - (int)$orderItem->getMpQtyRma());

                if ($qtyRma) {
                    if ($qtyRma <= 0 || $qtyRma > $qtyLeft) {
                        throw new InputException(__('qty_rma is invalid.'));
                    }
                } else {
                    $qtyRma = $qtyLeft;
                }

                $info = [
                    Item::REASON => $item->getReason() ?: '',
                    Item::SOLUTION => $item->getSolution() ?: '',
                    Item::ADDITIONAL_FIELDS => $this->prepareAdditionalField($item, $order)
                ];

                $productData['products'][$orderItem->getItemId()] = $this->prepareProductData(
                    $orderItem,
                    $qtyRma,
                    $info
                );
            }
        } else {
            $orderItem = $this->orderItemFactory->create()
                ->getCollection()
                ->addFieldToFilter('order_id', $order->getId());

            if (!isset($data[Request::REASON])) {
                throw new InputException(__('%1 is required.', Item::REASON));
            }

            if (!isset($data[Request::SOLUTION])) {
                throw new InputException(__('%1 is required.', Item::SOLUTION));
            }

            if (isset($data[Request::ADDITIONAL_FIELDS])) {
                foreach ($data[Request::ADDITIONAL_FIELDS] as $field) {
                    if ($field['is_require']) {
                        throw new InputException(__('%1 is required.', Item::ADDITIONAL_FIELDS));
                    }
                }
            }

            foreach ($orderItem as $item) {
                if (!$this->helperData->canReturnProduct($item->getProductId(), $order)) {
                    continue;
                }

                $qtyRma = ((int)$item->getQtyOrdered() - (int)$item->getMpQtyRma());

                $info = [
                    Item::REASON => $data[Request::REASON] ?: '',
                    Item::SOLUTION => $data[Request::SOLUTION] ?: '',
                    Item::ADDITIONAL_FIELDS => $this->prepareAdditionalField($item, $order)
                ];

                $productData['products'][$item->getItemId()] = $this->prepareProductData($item, $qtyRma, $info);
            }
        }

        return [
            Request::ORDER_ID => $order->getId(),
            Request::ORDER_INCREMENT_ID => $order->getIncrementId(),
            Request::STATUS_ID => isset($data[Request::STATUS_ID])
                ? $data[Request::STATUS_ID]
                : $this->helperData->getRequestConfig('default_status'),
            Request::IS_CANCELED => 0,
            Request::STORE_ID => $order->getStoreId(),
            Request::COMMENT => isset($data[Request::COMMENT]) ? $data[Request::COMMENT] : null,
            Request::FILES => isset($data[Request::FILES]) ? $data[Request::FILES] : null,
            Request::LAST_RESPONDED_BY => isset($data[Request::LAST_RESPONDED_BY])
                ? $data[Request::LAST_RESPONDED_BY]
                : null,
            Request::CUSTOMER_EMAIL => $order->getCustomerEmail(),
            'products' => HelperData::jsonEncode($productData['products'])
        ];
    }

    /**
     * @param Order\Item|DataObject $item
     * @param int $qtyRma
     * @param array $info
     *
     * @return array
     */
    public function prepareProductData($item, $qtyRma, $info)
    {
        return array_merge([
            Item::PRODUCT_ID => $item->getProductId(),
            Item::ITEM_ID => $item->getId(),
            Item::NAME => $item->getName(),
            Item::SKU => $item->getSku(),
            Item::QTY_RMA => $qtyRma,
            Item::PRICE => $price = $item->getPrice(),
            Item::PRICE_RETURNED => $this->helperData->getAvailableQtyToReturn($item) * $price
        ], $info);
    }

    /**
     * @param array $data
     * @param Order $order
     *
     * @return array
     * @throws InputException
     */
    public function prepareAdditionalField($data, $order)
    {
        $newData = [];

        if (!$data->getAdditionalFields()) {
            $fields = $this->helperData->getValidatedAdditionalFields($data->getProductId(), $order);

            foreach ($fields as $field) {
                $newData += [$field['value'] => ''];
            }

            return $newData;
        }

        foreach ($data->getAdditionalFields() as $item) {
            $this->helperData->checkRuleInfo(
                $item[ItemAdditionalFieldInterface::VALUE],
                $this->helperData->getAdditionalFieldOptionArray(),
                Item::ADDITIONAL_FIELDS
            );
            $newData += [
                $item[ItemAdditionalFieldInterface::VALUE] => $item[ItemAdditionalFieldInterface::CONTENT]
            ];
        }

        return $newData;
    }

    /**
     * @param string $value
     * @param string $config
     *
     * @return array
     */
    public function getDataInformation($value, $config)
    {
        $items = explode(',', $value);
        $data = [];

        if (count($items)) {
            foreach ($items as $item) {
                if ($item) {
                    $content = HelperData::jsonDecode($this->helperData->getRequestConfig($config))['name'];

                    if (isset($content[$item])) {
                        $data[] = [
                            'value' => $item,
                            'content' => $content[$item]
                        ];
                    }
                }
            }
        }

        return $data;
    }
}
