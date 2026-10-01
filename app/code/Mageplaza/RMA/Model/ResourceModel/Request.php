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

namespace Mageplaza\RMA\Model\ResourceModel;

use Magento\Framework\DB\Select;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\ItemFactory as OrderItemFactory;
use Magento\Sales\Model\ResourceModel\Order\Item as OrderItemResource;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\RMA\Helper\Conversation;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\Config\Source\System\Request\PatternType;
use Mageplaza\RMA\Model\Request as RequestModel;
use Mageplaza\RMA\Model\Request\Item;
use Mageplaza\RMA\Model\Request\ItemFactory;
use Mageplaza\RMA\Model\Request\Reply as ReplyModel;
use Mageplaza\RMA\Model\Request\ReplyFactory;
use Mageplaza\RMA\Model\ResourceModel\Request\Item as ItemResource;
use Mageplaza\RMA\Model\ResourceModel\Request\Reply as ReplyResource;
use Mageplaza\RMA\Model\Status as StatusModel;
use Mageplaza\RMA\Model\StatusFactory;

/**
 * Class Request
 * @package Mageplaza\RMA\Model\ResourceModel
 */
class Request extends AbstractDb
{
    /**
     * Default pattern for Sequence
     */
    const DEFAULT_PATTERN = "%'.09d";

    /**
     * @var string
     */
    protected $_requestShippingLabelTbl;

    /**
     * @var OrderItemFactory
     */
    protected $_orderItemFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var OrderItemResource
     */
    protected $_orderItemResource;

    /**
     * @var ItemFactory
     */
    protected $_itemFactory;

    /**
     * @var ItemResource
     */
    protected $_itemResource;

    /**
     * @var StatusFactory
     */
    protected $_statusFactory;

    /**
     * @var Status
     */
    protected $_statusResource;

    /**
     * @var ReplyFactory
     */
    protected $_replyFactory;

    /**
     * @var ReplyResource
     */
    protected $_replyResource;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * Request constructor.
     *
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param OrderItemFactory $orderItemFactory
     * @param OrderItemResource $orderItemResource
     * @param ItemFactory $itemFactory
     * @param ItemResource $itemResource
     * @param StatusFactory $statusFactory
     * @param Status $statusResource
     * @param ReplyFactory $replyFactory
     * @param ReplyResource $replyResource
     * @param HelperData $helperData
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        OrderItemFactory $orderItemFactory,
        OrderItemResource $orderItemResource,
        ItemFactory $itemFactory,
        ItemResource $itemResource,
        StatusFactory $statusFactory,
        Status $statusResource,
        ReplyFactory $replyFactory,
        ReplyResource $replyResource,
        HelperData $helperData,
        $connectionName = null
    ) {
        $this->_storeManager = $storeManager;
        $this->_orderItemFactory = $orderItemFactory;
        $this->_orderItemResource = $orderItemResource;
        $this->_itemFactory = $itemFactory;
        $this->_itemResource = $itemResource;
        $this->_statusFactory = $statusFactory;
        $this->_statusResource = $statusResource;
        $this->_replyFactory = $replyFactory;
        $this->_replyResource = $replyResource;
        $this->_helperData = $helperData;

        parent::__construct(
            $context,
            $connectionName
        );

        $this->_requestShippingLabelTbl = $this->getTable('mageplaza_rma_request_shipping_label');
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('mageplaza_rma_request', 'request_id');
    }

    /**
     * @param AbstractModel|RequestModel $object
     *
     * @return $this
     * @throws LocalizedException
     */
    public function _beforeSave(AbstractModel $object)
    {
        if (!$object->isObjectNew() && $object->getStatusId() !== $this->getOldRequestStatus($object)) {
            /** @var StatusModel $status */
            $status = $this->_statusFactory->create();
            $this->_statusResource->load($status, $object->getStatusId());
            if ($status->getEnableComment()) {
                /** @var ReplyModel $reply */
                $reply = $this->_replyFactory->create();
                $dataReply = [
                    'author_name' => __('Store Owner'),
                    'is_visible_on_front' => 1,
                    'type' => Conversation::TYPE_REPLY,
                    'content' => $status->getStoreComment($object->getStoreId()),
                    'request_id' => (int)$object->getId()
                ];
                $reply->addData($dataReply);
                $this->_replyResource->save($reply);
            }
        }

        return $this;
    }

    /**
     * @inheritdoc
     *
     * @param AbstractModel $object
     *
     * @return AbstractDb
     * @throws AlreadyExistsException
     * @throws LocalizedException
     */
    protected function _afterSave(AbstractModel $object)
    {
        $this->_saveRequestIncrementId($object);
        $this->_saveRequestItems($object);
        $this->_saveRequestShippingLabel($object);

        return parent::_afterSave($object);
    }

    /**
     * @param AbstractModel|RequestModel $object
     *
     * @return $this
     * @throws LocalizedException
     */
    protected function _saveRequestIncrementId($object)
    {
        if ($object->getIncrementId() === null) {
            $requestId = $object->getId();
            $incrementId = sprintf(self::DEFAULT_PATTERN, $requestId);
            $patternType = (int)$this->_helperData->getConfigGeneral('rma_pattern/type');
            if ($patternType === PatternType::CUSTOM) {
                $prefix = $this->_helperData->getConfigGeneral('rma_pattern/prefix');
                $prefix = $prefix ? $prefix . '-' : '';
                $suffix = $this->_helperData->getConfigGeneral('rma_pattern/suffix');
                $suffix = $suffix ? '-' . $suffix : '';
                $incrementId = $prefix . $incrementId . $suffix;
            }

            $this->getConnection()->update(
                $this->getMainTable(),
                ['increment_id' => $incrementId],
                ['request_id = ?' => (int)$requestId]
            );
        }

        return $this;
    }

    /**
     * @param AbstractModel|RequestModel $object
     *
     * @return $this
     * @throws AlreadyExistsException
     */
    protected function _saveRequestItems($object)
    {
        $requestId = $object->getId();
        $items = HelperData::jsonDecode($object->getProducts());
        if (empty($items)) {
            return $this;
        }
        /** @var array $items */
        foreach ($items as $item) {
            $data = [
                'request_id' => (int)$requestId,
                'product_id' => (int)$item['product_id'],
                'order_item_id' => (int)$item['item_id'],
                'name' => $item['name'],
                'sku' => $item['sku'],
                'price' => $item['price'],
                'qty_rma' => (int)$item['qty_rma'],
                'price_returned' => $item['price_returned'],
                'reason' => $this->_getReasonLabelByValue($item),
                'solution' => $this->_getSolutionLabelByValue($item),
                'additional_fields' => $this->_getFieldsInformationByValue($item)
            ];
            /** @var Item $requestItem */
            $requestItem = $this->_itemFactory->create();
            $requestItem->addData($data);
            $this->_itemResource->save($requestItem);

            /** @var OrderItem $orderItem */
            $orderItem = $this->_orderItemFactory->create();
            $this->_orderItemResource->load($orderItem, $item['item_id']);
            $currentRmaQty = (float)$orderItem->getMpQtyRma();
            $newRmaQty = $currentRmaQty + (float)$item['qty_rma'];
            $orderItem->setMpQtyRma($newRmaQty);
            $this->_orderItemResource->save($orderItem);
        }

        return $this;
    }

    /**
     * @param AbstractModel|RequestModel $object
     *
     * @return $this
     */
    protected function _saveRequestShippingLabel($object)
    {
        $requestId = $object->getId();
        $shippingLabelId = $object->getShippingLabelId();
        $adapter = $this->getConnection();
        $adapter->delete($this->_requestShippingLabelTbl, ['request_id=?' => (int)$requestId]);
        if (empty($shippingLabelId)) {
            return $this;
        }
        $adapter->insert($this->_requestShippingLabelTbl, [
            'request_id' => (int)$requestId,
            'shipping_label_id' => (int)$shippingLabelId
        ]);

        return $this;
    }

    /**
     * @param AbstractModel|RequestModel $object
     *
     * @return AbstractDb
     */
    protected function _beforeDelete(AbstractModel $object)
    {
        $items = $object->getItemsCollection();
        foreach ($items as $item) {
            /** @var Item $item */
            $oldQtyRma = $item->getQtyRma();
            /** @var OrderItem $orderItem */
            $orderItem = $this->_orderItemFactory->create();
            $this->_orderItemResource->load($orderItem, $item->getOrderItemId());
            $currentRmaQty = (float)$orderItem->getMpQtyRma();
            $newRmaQty = $currentRmaQty - (float)$oldQtyRma;
            $orderItem->setMpQtyRma($newRmaQty);
            $this->_orderItemResource->save($orderItem);
        }

        return parent::_beforeDelete($object);
    }

    /**
     * @param array $item
     *
     * @return string
     */
    protected function _getReasonLabelByValue($item)
    {
        $reasonLabel = '';
        if (isset($item['reason'])) {
            $allReasons = $this->_helperData->getReasonOptionArray();
            foreach ($allReasons as $reason) {
                if ($reason['value'] === $item['reason']) {
                    $reasonLabel = $reason['label'];
                    break;
                }
            }
        }

        return $reasonLabel;
    }

    /**
     * @param array $item
     *
     * @return string
     */
    protected function _getSolutionLabelByValue($item)
    {
        $solutionLabel = '';
        if (isset($item['solution'])) {
            $allSolutions = $this->_helperData->getSolutionOptionArray();
            foreach ($allSolutions as $solution) {
                if ($solution['value'] === $item['solution']) {
                    $solutionLabel = $solution['label'];
                    break;
                }
            }
        }

        return $solutionLabel;
    }

    /**
     * @param array $item
     *
     * @return string
     */
    protected function _getFieldsInformationByValue($item)
    {
        $chosenField = [];
        if (isset($item['additional_fields'])) {
            $allFields = $this->_helperData->getAdditionalFieldOptionArray();
            foreach ($allFields as $field) {
                /** @var array[] $item */
                foreach ($item['additional_fields'] as $fieldValue => $fieldContent) {
                    if ($field['value'] === $fieldValue) {
                        $field['content'] = $fieldContent;
                        $chosenField[] = $field;
                    }
                }
            }
        }

        return HelperData::jsonEncode($chosenField);
    }

    /**
     * @param RequestModel $request
     *
     * @return Select
     */
    public function getSelectShippingLabel($request)
    {
        return $this->getConnection()->select()->from(
            $this->_requestShippingLabelTbl,
            'shipping_label_id'
        )
            ->where(
                'request_id = ?',
                (int)$request->getId()
            );
    }

    /**
     * @param RequestModel $request
     *
     * @return string
     */
    public function getShippingLabelId($request)
    {
        return $this->getConnection()->fetchOne($this->getSelectShippingLabel($request));
    }

    /**
     * @param RequestModel $request
     *
     * @return array
     */
    public function getRequestShippingLabel($request)
    {
        return $this->getConnection()->fetchAll($this->getSelectShippingLabel($request));
    }

    /**
     * @param AbstractModel $object
     *
     * @return string
     * @throws LocalizedException
     */
    public function getOldRequestStatus($object)
    {
        $adapter = $this->getConnection();
        $select = $adapter->select()->from(
            $this->getMainTable(),
            'status_id'
        )
            ->where(
                'request_id = ?',
                (int)$object->getId()
            );

        return $adapter->fetchOne($select);
    }
}
