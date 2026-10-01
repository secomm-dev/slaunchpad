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

use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Sales\Model\Order\ItemFactory as OrderItemFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Mageplaza\RMA\Api\Data\GuestDataInterface as GuestData;
use Mageplaza\RMA\Api\Data\RequestInterface;
use Mageplaza\RMA\Api\Data\RequestReplyInterface;
use Mageplaza\RMA\Api\RequestManagementInterface;
use Mageplaza\RMA\Api\SearchResult\RequestSearchResultInterface;
use Mageplaza\RMA\Api\SearchResult\RequestSearchResultInterfaceFactory;
use Mageplaza\RMA\Block\Customer\Request as BlockRequest;
use Mageplaza\RMA\Helper\Conversation;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\Request;
use Mageplaza\RMA\Model\Request\ReplyFactory;
use Mageplaza\RMA\Model\RequestFactory;
use Mageplaza\RMA\Model\ResourceModel\Request as RequestResource;
use Mageplaza\RMA\Model\ResourceModel\Request\Collection;
use Mageplaza\RMA\Model\ResourceModel\Request\CollectionFactory;
use Mageplaza\RMA\Model\ResourceModel\Request\Reply as ReplyResource;
use Mageplaza\RMA\Model\ResourceModel\Status as StatusResource;

/**
 * Class RequestManagement
 * @package Mageplaza\RMA\Model\Api
 */
class RequestManagement extends AbstractManagement implements RequestManagementInterface
{
    /**
     * @var RequestFactory
     */
    protected $requestFactory;

    /**
     * @var RequestSearchResultInterfaceFactory
     */
    protected $requestSearchResultFactory;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var OrderCollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var RequestResource
     */
    protected $requestResource;

    /**
     * @var ReplyFactory
     */
    protected $replyFactory;

    /**
     * @var ReplyResource
     */
    protected $replyResource;

    /**
     * @var BlockRequest
     */
    protected $blockRequest;

    /**
     * @var StatusResource
     */
    protected $statusResource;

    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * RequestManagement constructor.
     *
     * @param HelperData $helperData
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CollectionProcessorInterface $collectionProcessor
     * @param OrderItemFactory $orderItemFactory
     * @param OrderFactory $orderFactory
     * @param RequestFactory $requestFactory
     * @param RequestSearchResultInterfaceFactory $requestSearchResultFactory
     * @param CollectionFactory $collectionFactory
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param RequestResource $requestResource
     * @param ReplyFactory $replyFactory
     * @param ReplyResource $replyResource
     * @param BlockRequest $blockRequest
     * @param StatusResource $statusResource
     * @param CustomerFactory $customerFactory
     */
    public function __construct(
        HelperData $helperData,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CollectionProcessorInterface $collectionProcessor,
        OrderItemFactory $orderItemFactory,
        OrderFactory $orderFactory,
        RequestFactory $requestFactory,
        RequestSearchResultInterfaceFactory $requestSearchResultFactory,
        CollectionFactory $collectionFactory,
        OrderCollectionFactory $orderCollectionFactory,
        RequestResource $requestResource,
        ReplyFactory $replyFactory,
        ReplyResource $replyResource,
        BlockRequest $blockRequest,
        StatusResource $statusResource,
        CustomerFactory $customerFactory
    ) {
        $this->requestFactory             = $requestFactory;
        $this->requestSearchResultFactory = $requestSearchResultFactory;
        $this->collectionFactory          = $collectionFactory;
        $this->orderCollectionFactory     = $orderCollectionFactory;
        $this->requestResource            = $requestResource;
        $this->replyFactory               = $replyFactory;
        $this->replyResource              = $replyResource;
        $this->blockRequest               = $blockRequest;
        $this->statusResource             = $statusResource;
        $this->customerFactory            = $customerFactory;

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
        return $this->deleteEntity($this->requestFactory->create(), $id, $this->requestResource);
    }

    /**
     * @inheritdoc
     */
    public function save(RequestInterface $entity)
    {
        $this->checkEnabled();
        $this->checkRequired([Request::ORDER_INCREMENT_ID], $entity->getData());

        return $this->processSave($entity, 'admin');
    }

    /**
     * @inheritdoc
     */
    public function saveMine(RequestInterface $entity)
    {
        $this->checkEnabled();
        $required = [Request::ORDER_INCREMENT_ID];

        if (!$this->helperData->isReturnEachItem()) {
            array_push($required, Request::REASON, Request::SOLUTION, Request::ADDITIONAL_FIELDS);
        }

        $this->checkRequired($required, $entity->getData());

        if ($entity->getStatusId()) {
            throw new InputException(__('%1 is invalid', Request::STATUS_ID));
        }

        if (!$this->helperData->isUploadFiles() && $entity->getUpload()) {
            throw new InputException(__('Not allow upload attachment.'));
        }

        if ($entity->getRequestId()) {
            throw new InputException(__('Cannot edit request.'));
        }

        return $this->processSave($entity);
    }

    /**
     * @inheritdoc
     */
    public function saveGuest(RequestInterface $entity)
    {
        $this->checkEnabled();

        if (!$this->helperData->getConfigGeneral('enabled_guest')) {
            throw new InputException(__('Not allow RMA Request'));
        }

        $required = [Request::ORDER_INCREMENT_ID, Request::GUEST_DATA];

        if (!$this->helperData->isReturnEachItem()) {
            array_push($required, Request::REASON, Request::SOLUTION, Request::ADDITIONAL_FIELDS);
        }

        $this->checkRequired($required, $entity->getData());

        if ($entity->getStatusId()) {
            throw new InputException(__('%1 is invalid', Request::STATUS_ID));
        }

        $this->checkGuestData($entity->getOrderIncrementId(), $entity->getGuestData());

        if (!$this->helperData->isUploadFiles() && $entity->getUpload()) {
            throw new InputException(__('Not allow upload attachment.'));
        }

        if ($entity->getRequestId()) {
            throw new InputException(__('Cannot edit request.'));
        }

        return $this->processSave($entity);
    }

    /**
     * @param RequestInterface $entity
     * @param string $api
     *
     * @return mixed
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws StateException
     */
    public function processSave($entity, $api = null)
    {
        $this->orderFactory->create()->loadByIncrementId($entity->getOrderIncrementId());
        $requestModel = $this->requestFactory->create();

        if ($entity->getStatusId() || $entity->getStatusId() === 0) {
            $statusIds = $this->statusResource->getStatusIds();
            if (!in_array((string)$entity->getStatusId(), $statusIds, true)) {
                throw new InputException(__('%1 is invalid', Request::STATUS_ID));
            }
        }

        if ($entity->getUpload()) {
            $entity->setFiles($this->helperData->uploadMultiFiles($entity->getUpload()));
        }

        return $this->saveEntity(
            $entity,
            $requestModel,
            'request',
            $this->requestResource,
            $api
        );
    }

    /**
     * @inheritdoc
     */
    public function getList(?SearchCriteriaInterface $searchCriteria = null)
    {
        /** @var RequestSearchResultInterface $searchResult */
        $searchResult = $this->requestSearchResultFactory->create();
        /** @var Collection $ruleCollection */
        $ruleCollection = $this->collectionFactory->create();

        return $this->getListEntity($searchCriteria, $searchResult, $ruleCollection);
    }

    /**
     * @inheritdoc
     */
    public function getMine($customerId, ?SearchCriteriaInterface $searchCriteria = null)
    {
        /** @var RequestSearchResultInterface $searchResult */
        $searchResult = $this->requestSearchResultFactory->create();

        /** @var OrderCollection $orderCollection */
        $orderCollection = $this->orderCollectionFactory->create()
            ->addAttributeToSelect('entity_id')
            ->addAttributeToFilter('customer_id', $customerId);

        $orderIds = [];
        foreach ($orderCollection->getData() as $item) {
            $orderIds[] = $item['entity_id'];
        }

        /** @var Collection $ruleCollection */
        $ruleCollection = $this->collectionFactory->create()->addFieldToFilter('order_id', ['in' => $orderIds]);

        return $this->getListEntity($searchCriteria, $searchResult, $ruleCollection);
    }

    /**
     * @inheritdoc
     */
    public function saveAdminReply(RequestReplyInterface $reply)
    {
        $this->checkEnabled();
        $this->checkRequired([Request::REQUEST_ID], $reply->getData());
        $replyModel = $this->replyFactory->create();
        $this->validateReply($reply, $this->helperData->getReplyAgentName());

        if ($reply->getIsVisibleOnFront() === 1) {
            $reply->setType(Conversation::TYPE_REPLY);
        } else {
            $reply->setType(Conversation::TYPE_NOTE);
        }

        if ($reply->getUpload()) {
            $reply->setFiles($this->helperData->uploadMultiFiles($reply->getUpload(), 'reply'));
        }

        $result = $this->saveEntity(
            $reply,
            $replyModel,
            'replyAdmin',
            $this->replyResource
        );
        $this->saveLastRequest($result);

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function saveCustomerReply($customerId, RequestReplyInterface $reply)
    {
        $this->checkEnabled();
        $this->checkRequired([Request::REQUEST_ID], $reply->getData());
        $this->checkCustomerRequest($customerId, $reply->getRequestId());
        $replyModel = $this->replyFactory->create();
        $this->validateReply($reply, $this->getCustomerName($customerId), 1);
        $reply->setType(Conversation::TYPE_CUSTOMER_RESPONSE);

        if ($reply->getUpload()) {
            $reply->setFiles($this->helperData->uploadMultiFiles($reply->getUpload(), 'reply'));
        }

        $result = $this->saveEntity(
            $reply,
            $replyModel,
            'replyCustomer',
            $this->replyResource
        );

        $this->saveLastRequest($result);

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function cancel($customerId, RequestReplyInterface $reply)
    {
        $this->checkEnabled();
        $this->checkRequired([Request::REQUEST_ID], $reply->getData());
        $this->checkCustomerRequest($customerId, $reply->getRequestId());
        $replyModel = $this->replyFactory->create();
        $request = $this->requestFactory->create();
        $this->requestResource->load($request, $reply->getRequestId());

        if (!$this->blockRequest->canCancelRequest($request)) {
            throw new InputException(__('This request cannot be canceled.'));
        }

        if ($request->getIsCanceled() === '1') {
            throw new InputException(__('The request has canceled.'));
        }

        $reply->setAuthorName(__('Customer'));
        $reply->setType(Conversation::TYPE_CUSTOMER_RESPONSE);
        $reply->setIsVisibleOnFront(1);
        $reply->setContent(__('Customer has been canceled this request.'));

        $this->saveEntity(
            $reply,
            $replyModel,
            'cancel',
            $this->replyResource
        );

        $request->setIsCanceled(1);
        $this->requestResource->save($request);

        return true;
    }

    /**
     * @inheritdoc
     *
     * @throws InputException
     */
    public function validateReply($reply, $authorName, $isVisible = 0)
    {
        if (!$reply->getAuthorName()) {
            $reply->setAuthorName($authorName);
        }

        if ($reply->getIsCustomerNotified()) {
            $this->helperData->checkYesNo($reply->getIsCustomerNotified(), Request\Reply::IS_CUSTOMER_NOTIFIED);
        } else {
            $reply->setIsCustomerNotified(0);
        }

        if ($reply->getIsVisibleOnFront()) {
            $this->helperData->checkYesNo($reply->getIsVisibleOnFront(), Request\Reply::IS_VISIBLE_ON_FRONT);
        } else {
            $reply->setIsVisibleOnFront($isVisible);
        }

        if ($reply->getReplyId() && $reply->getCreatedAt()) {
            throw new InputException(__('Cannot modify created at.'));
        }
    }

    /**
     * @param string $customerId
     * @param string $requestId
     *
     * @throws InputException
     */
    public function checkCustomerRequest($customerId, $requestId)
    {
        $customerEmail = $this->customerFactory->create()->load($customerId)->getEmail();
        $requestEmail  = $this->requestFactory->create()->load($requestId)->getCustomerEmail();

        if ($customerEmail !== $requestEmail) {
            throw new InputException(__('This request ID does not belong to this customer.'));
        }
    }

    /**
     * @param string $orderIncrement
     * @param GuestData|array $data
     *
     * @throws InputException
     */
    public function checkGuestData($orderIncrement, $data)
    {
        $order = $this->orderFactory->create()->loadByIncrementId($orderIncrement);

        if (!$order->getId()) {
            throw new InputException(__('%1 is invalid.', Request::ORDER_INCREMENT_ID));
        }

        if (is_array($data)) {
            $findBy = $data['find_by'];
            $billingLastName = $data['billing_last_name'];
        } else {
            $findBy = $data->getFindBy();
            $billingLastName = $data->getBillingLastName();
        }

        $this->requiredGuestData($billingLastName, GuestData::BILLING_LAST_NAME);
        $this->requiredGuestData($findBy, GuestData::FIND_BY);

        if ($findBy !== GuestData::EMAIL && $findBy !== GuestData::ZIP_CODE) {
            throw new InputException(
                __('%1 should be %2 or %3', GuestData::FIND_BY, GuestData::EMAIL, GuestData::ZIP_CODE)
            );
        }

        $billingAddr = $order->getBillingAddress();

        if (!$billingAddr || $billingAddr->getLastname() !== $billingLastName) {
            throw new InputException(__('You entered incorrect data. Please try again.'));
        }

        if ($findBy === GuestData::EMAIL) {
            if (is_array($data)) {
                if (isset($data['email'])) {
                    $email = $data['email'];
                } else {
                    throw new InputException(__('%1 is required.', GuestData::EMAIL));
                }
            } else {
                $email = $data->getEmail();
            }

            $this->requiredGuestData($email, GuestData::EMAIL);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InputException(__('Email address is invalid.'));
            }

            if ($email !== $billingAddr->getEmail()) {
                throw new InputException(__('You entered incorrect data. Please try again.'));
            }
        }

        if ($findBy === GuestData::ZIP_CODE) {
            if (is_array($data)) {
                if (isset($data[GuestData::ZIP_CODE])) {
                    $zipCode = $data[GuestData::ZIP_CODE];
                } else {
                    throw new InputException(__('%1 is required.', GuestData::ZIP_CODE));
                }
            } else {
                $zipCode = $data->getZipCode();
            }

            $this->requiredGuestData($zipCode, GuestData::ZIP_CODE);

            if ($zipCode !== $billingAddr->getPostcode()) {
                throw new InputException(__('You entered incorrect data. Please try again.'));
            }
        }
    }

    /**
     * @param Request $request
     *
     * @throws AlreadyExistsException
     */
    public function saveLastRequest($request)
    {
        $rmaRequest = $this->requestFactory->create();
        $this->requestResource->load($rmaRequest, $request->getRequestId());
        $respondedBy = $request->getAuthorName() . ' (' . $rmaRequest->getCustomerEmail() . ')';
        $rmaRequest->setLastRespondedBy($respondedBy);
        $this->requestResource->save($rmaRequest);
    }
}
