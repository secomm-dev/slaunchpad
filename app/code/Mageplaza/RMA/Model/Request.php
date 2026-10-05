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

namespace Mageplaza\RMA\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Mageplaza\RMA\Api\Data\RequestInterface;
use Mageplaza\RMA\Model\ResourceModel\Request as RequestResource;
use Mageplaza\RMA\Model\ResourceModel\Request\Item\Collection as ItemCollection;
use Mageplaza\RMA\Model\ResourceModel\Request\Item\CollectionFactory as ItemColFact;
use Mageplaza\RMA\Model\ResourceModel\Request\Reply\Collection as ReplyCollection;
use Mageplaza\RMA\Model\ResourceModel\Request\Reply\CollectionFactory as ReplyColFact;
use Mageplaza\RMA\Model\ResourceModel\ShippingLabel as ShippingLabelResource;
use Mageplaza\RMA\Model\ResourceModel\Status as StatusResource;
use Magento\Sales\Api\OrderItemRepositoryInterface;

/**
 * Class Request
 * @method Request setShippingLabelId($shippingLabelId)
 * @method string getProducts()
 * @package Mageplaza\RMA\Model
 */
class Request extends AbstractModel implements RequestInterface
{
    /**
     * Cache tag
     *
     * @var string
     */
    const CACHE_TAG = 'mageplaza_rma_request';

    /**
     * Cache tag
     *
     * @var string
     */
    protected $_cacheTag = 'mageplaza_rma_request';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'mageplaza_rma_request';

    /**
     * @var string
     */
    protected $_idFieldName = 'request_id';

    /**
     * @var OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var OrderResource
     */
    protected $_orderResource;

    /**
     * @var RequestResource
     */
    protected $_requestResource;

    /**
     * @var ItemColFact
     */
    protected $_requestItemColFact;

    /**
     * @var ReplyColFact
     */
    protected $_requestReplyColFact;

    /**
     * @var StatusFactory
     */
    protected $_requestStatusFactory;

    /**
     * @var StatusResource
     */
    protected $_requestStatusResource;

    /**
     * @var ShippingLabelFactory
     */
    protected $_shippingLabelFact;

    /**
     * @var ShippingLabelResource
     */
    protected $_shippingLabelResource;

    /**
     * @var OrderItemRepositoryInterface
     */
    protected $orderItemRepository;

    /**
     * Request constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param OrderFactory $orderFactory
     * @param OrderResource $orderResource
     * @param RequestResource $requestResource
     * @param ItemColFact $requestItemColFact
     * @param ReplyColFact $requestReplyColFact
     * @param StatusFactory $requestStatusFactory
     * @param StatusResource $requestStatusResource
     * @param ShippingLabelFactory $shippingLabelFact
     * @param ShippingLabelResource $shippingLabelResource
     * @param OrderItemRepositoryInterface $orderItemRepository
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        OrderFactory $orderFactory,
        OrderResource $orderResource,
        RequestResource $requestResource,
        ItemColFact $requestItemColFact,
        ReplyColFact $requestReplyColFact,
        StatusFactory $requestStatusFactory,
        StatusResource $requestStatusResource,
        ShippingLabelFactory $shippingLabelFact,
        ShippingLabelResource $shippingLabelResource,
        OrderItemRepositoryInterface $orderItemRepository,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_orderFactory          = $orderFactory;
        $this->_orderResource         = $orderResource;
        $this->_requestResource       = $requestResource;
        $this->_requestItemColFact    = $requestItemColFact;
        $this->_requestReplyColFact   = $requestReplyColFact;
        $this->_requestStatusFactory  = $requestStatusFactory;
        $this->_requestStatusResource = $requestStatusResource;
        $this->_shippingLabelFact     = $shippingLabelFact;
        $this->_shippingLabelResource = $shippingLabelResource;
        $this->orderItemRepository    = $orderItemRepository;

        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(RequestResource::class);
    }

    /**
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * @return ItemCollection
     */
    public function getItemsCollection()
    {
        /** @var ItemCollection $collection */
        $collection = $this->_requestItemColFact->create();
        $requestId  = $this->getId();
        $collection->addFieldToFilter('request_id', $requestId);

        return $collection;
    }

    /**
     * @param bool $isFront
     *
     * @return ReplyCollection
     */
    public function getReplyCollection($isFront = false)
    {
        /** @var ReplyCollection $collection */
        $collection = $this->_requestReplyColFact->create();
        $requestId  = $this->getId();
        $collection->addFieldToFilter('request_id', $requestId)
            ->setOrder('main_table.created_at', 'desc');
        if ($isFront) {
            $collection->addFieldToFilter('is_visible_on_front', 1);
        }

        return $collection;
    }

    /**
     * @return mixed
     * @throws LocalizedException
     */
    public function getStatusLabel()
    {
        $area = $this->_appState->getAreaCode();

        /** @var Status $status */
        $status = $this->getStatus();
        if ($area === 'adminhtml') {
            return $status->getLabel();
        }

        return $status->getStoreLabel();
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        /** @var Order $order */
        $order = $this->_orderFactory->create();
        $this->_orderResource->load($order, $this->getOrderId());

        return $order;
    }

    /**
     * @return Status
     */
    public function getStatus()
    {
        /** @var Status $status */
        $status = $this->_requestStatusFactory->create();
        $this->_requestStatusResource->load($status, $this->getStatusId());

        return $status;
    }

    /**
     * @return string
     */
    public function getShippingLabelId()
    {
        if (!$this->hasData('shipping_label_id')) {
            $id = $this->_requestResource->getShippingLabelId($this);
            $this->setData('shipping_label_id', $id);
        }

        return $this->_getData('shipping_label_id');
    }

    /**
     * @return bool|ShippingLabel
     */
    public function getShippingLabel()
    {
        if (!$this->getShippingLabelId()) {
            return false;
        }
        /** @var ShippingLabel $shippingLabel */
        $shippingLabel = $this->_shippingLabelFact->create();
        $this->_shippingLabelResource->load($shippingLabel, $this->getShippingLabelId());

        return $shippingLabel;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestId()
    {
        return $this->getData(self::REQUEST_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setRequestId($value)
    {
        return $this->setData(self::REQUEST_ID, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderId()
    {
        return $this->getData(self::ORDER_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setOrderId($value)
    {
        return $this->setData(self::ORDER_ID, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderIncrementId()
    {
        return $this->getData(self::ORDER_INCREMENT_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setOrderIncrementId($value)
    {
        return $this->setData(self::ORDER_INCREMENT_ID, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getIncrementId()
    {
        return $this->getData(self::INCREMENT_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setIncrementId($value)
    {
        return $this->setData(self::INCREMENT_ID, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusId()
    {
        return $this->getData(self::STATUS_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setStatusId($value)
    {
        return $this->setData(self::STATUS_ID, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getIsCanceled()
    {
        return $this->getData(self::IS_CANCELED);
    }

    /**
     * {@inheritdoc}
     */
    public function setIsCanceled($value)
    {
        return $this->setData(self::IS_CANCELED, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getStoreId()
    {
        return $this->getData(self::STORE_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setStoreId($value)
    {
        return $this->setData(self::STORE_ID, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getComment()
    {
        return $this->getData(self::COMMENT);
    }

    /**
     * {@inheritdoc}
     */
    public function setComment($value)
    {
        return $this->setData(self::COMMENT, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getFiles()
    {
        return $this->getData(self::FILES);
    }

    /**
     * {@inheritdoc}
     */
    public function setFiles($value)
    {
        return $this->setData(self::FILES, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getLastRespondedBy()
    {
        return $this->getData(self::LAST_RESPONDED_BY);
    }

    /**
     * {@inheritdoc}
     */
    public function setLastRespondedBy($value)
    {
        return $this->setData(self::LAST_RESPONDED_BY, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomerEmail()
    {
        return $this->getData(self::CUSTOMER_EMAIL);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerEmail($value)
    {
        return $this->setData(self::CUSTOMER_EMAIL, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * {@inheritdoc}
     */
    public function setUpdatedAt($value)
    {
        return $this->setData(self::UPDATED_AT, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * {@inheritdoc}
     */
    public function setCreatedAt($value)
    {
        return $this->setData(self::CREATED_AT, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestItem()
    {
        $data = [];
        $order = $this->_orderFactory->create();
        foreach($this->getItemsCollection()->getItems() as $item){
            $orderId = $this->orderItemRepository->get($item->getOrderItemId())->getOrderId();
            $order = $order->load($orderId);
            $data[] = [
                'product_id'        => $item->getProductId(),
                'name'              => $item->getName(),
                'sku'               => $item->getSku(),
                'item_id'           => $item->getItemId(),
                'request_id'        => $item->getRequestId(),
                'order_item_id'     => $item->getOrderItemId(),
                'qty_rma'           => $item->getQtyRma(),
                'price'             => $item->getPrice(),
                'price_returned'    => $item->getPriceReturned(),
                'reason'            => $item->getReason(),
                'solution'          => $item->getSolution(),
                'additional_fields' => $item->getAdditionalFields(),
                'created_at'        => $item->getCreated_at(),
                'currency'          => $order->getOrderCurrencyCode()
            ];
        }
        if (!$this->hasData(self::REQUEST_ITEM)) {
            $this->setData(self::REQUEST_ITEM, $data);
        }
        return $this->getData(self::REQUEST_ITEM);
    }

    /**
     * {@inheritdoc}
     */
    public function setRequestItem($value)
    {
        return $this->setData(self::REQUEST_ITEM, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestReply()
    {
        if (!$this->hasData(self::REQUEST_REPLY)) {
            $this->setData(self::REQUEST_REPLY, $this->getReplyCollection()->getItems());
        }

        return $this->getData(self::REQUEST_REPLY);
    }

    /**
     * {@inheritdoc}
     */
    public function setRequestReply($value)
    {
        return $this->setData(self::REQUEST_REPLY, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestShippingLabel()
    {
        if (!$this->hasData(self::REQUEST_SHIPPING_LABEL)) {
            $this->setData(self::REQUEST_SHIPPING_LABEL, $this->_requestResource->getRequestShippingLabel($this));
        }

        return $this->getData(self::REQUEST_SHIPPING_LABEL);
    }

    /**
     * {@inheritdoc}
     */
    public function setRequestShippingLabel($value)
    {
        return $this->setData(self::REQUEST_SHIPPING_LABEL, $value);
    }

    /**
     * @inheritDoc
     */
    public function getUpload()
    {
        return $this->getData(self::UPLOAD);
    }

    /**
     * @inheritDoc
     */
    public function setUpload($value)
    {
        return $this->setData(self::UPLOAD, $value);
    }

    /**
     * @inheritDoc
     */
    public function getGuestData()
    {
        return $this->getData(self::GUEST_DATA);
    }

    /**
     * @inheritDoc
     */
    public function setGuestData($value)
    {
        return $this->setData(self::GUEST_DATA, $value);
    }

    /**
     * @inheritDoc
     */
    public function getReason()
    {
        return $this->getData(self::REASON);
    }

    /**
     * @inheritDoc
     */
    public function setReason($value)
    {
        return $this->setData(self::REASON, $value);
    }

    /**
     * @inheritDoc
     */
    public function getSolution()
    {
        return $this->getData(self::SOLUTION);
    }

    /**
     * @inheritDoc
     */
    public function setSolution($value)
    {
        return $this->setData(self::SOLUTION, $value);
    }

    /**
     * @inheritDoc
     */
    public function getAdditionalFields()
    {
        return $this->getData(self::ADDITIONAL_FIELDS);
    }

    /**
     * @inheritDoc
     */
    public function setAdditionalFields($value)
    {
        return $this->setData(self::ADDITIONAL_FIELDS, $value);
    }
}
