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

namespace Mageplaza\RMA\Controller\Adminhtml\Request\Reply;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Messages;
use Magento\Framework\View\Layout;
use Magento\Sales\Model\Order;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\RMA\Block\Request\View\Reply;
use Mageplaza\RMA\Helper\Conversation as HelperConversation;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Helper\Image;
use Mageplaza\RMA\Model\Request as RequestModel;
use Mageplaza\RMA\Model\Request\Reply as ReplyModel;
use Mageplaza\RMA\Model\Request\ReplyFactory;
use Mageplaza\RMA\Model\RequestFactory;
use Mageplaza\RMA\Model\ResourceModel\Request as RequestResource;
use Mageplaza\RMA\Model\ResourceModel\Request\Reply as ReplyResource;

/**
 * Class Save
 * @package Mageplaza\RMA\Controller\Adminhtml\Request\Template
 */
class Save extends Action
{
    /** Authorization level of a basic admin session */
    const ADMIN_RESOURCE = 'Mageplaza_RMA::request';

    /**
     * @var ForwardFactory
     */
    protected $_resultFwFactory;

    /**
     * @var Layout
     */
    protected $_layout;

    /**
     * @var Json
     */
    protected $_resultJson;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var ReplyFactory
     */
    protected $_replyFactory;

    /**
     * @var ReplyResource
     */
    protected $_replyResource;

    /**
     * @var RequestFactory
     */
    protected $_requestFactory;

    /**
     * @var RequestResource
     */
    protected $_requestResource;

    /**
     * @var Image
     */
    protected $_helperImage;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * Save constructor.
     *
     * @param Context $context
     * @param ForwardFactory $resultFwFactory
     * @param Layout $layout
     * @param Json $resultJson
     * @param StoreManagerInterface $storeManager
     * @param EncryptorInterface $encryptor
     * @param ReplyFactory $replyFactory
     * @param ReplyResource $replyResource
     * @param RequestFactory $requestFactory
     * @param RequestResource $requestResource
     * @param Image $helperImage
     * @param HelperData $helperData
     */
    public function __construct(
        Context $context,
        ForwardFactory $resultFwFactory,
        Layout $layout,
        Json $resultJson,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encryptor,
        ReplyFactory $replyFactory,
        ReplyResource $replyResource,
        RequestFactory $requestFactory,
        RequestResource $requestResource,
        Image $helperImage,
        HelperData $helperData
    ) {
        $this->_resultFwFactory = $resultFwFactory;
        $this->_layout = $layout;
        $this->_resultJson = $resultJson;
        $this->_storeManager = $storeManager;
        $this->_encryptor = $encryptor;
        $this->_replyFactory = $replyFactory;
        $this->_replyResource = $replyResource;
        $this->_requestFactory = $requestFactory;
        $this->_requestResource = $requestResource;
        $this->_helperImage = $helperImage;
        $this->_helperData = $helperData;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     * @throws LocalizedException
     * @throws AlreadyExistsException
     */
    public function execute()
    {
        /** @var Http $request */
        $request = $this->getRequest();
        if (!$request->isAjax()) {
            return $this->_resultFwFactory->create()->forward('noroute');
        }
        /** @var Messages $messageBlock */
        $messageBlock = $this->_layout->createBlock(Messages::class);
        if ((!$data = $request->getPost('reply')) || (!$requestData = $request->getPost('request'))) {
            $messageBlock->addError(__('Cannot find request reply data.'));
            $result = [
                'status' => false,
                'message' => $messageBlock->toHtml()
            ];

            return $this->_resultJson->setData($result);
        }

        $data = $request->getPost('reply');
        $requestData = $request->getPost('request');
        /** @var ReplyModel $reply */
        $reply = $this->_replyFactory->create();

        /**
         * Save request last responded name
         * @var RequestModel $rmaRequest
         */
        $rmaRequest = $this->_requestFactory->create();
        $this->_requestResource->load($rmaRequest, (int)$requestData['request_id']);
        if (!$rmaRequest->getData()) {
            return $this->_resultJson->setData(['requestRedirect' => $this->_url->getUrl('mprma/request/index')]);
        }
        $respondedBy = $data['author_name'] . ' (' . $data['author_email'] . ')';

        /** Upload files */

        if (isset($data['files']) && count($data['files'])) {
            $data['files'] = HelperData::jsonEncode($this->_helperImage->processImagesGallery($data['files']));
        }
        $data['request_id'] = (int)$requestData['request_id'];
        $this->prepareData($reply, $data);

        $rmaRequest->setLastRespondedBy($respondedBy);
        if ($reply->getIsCustomerNotified()) {
            $rmaRequest->setUpdatedAt($this->_helperData->getCurrentDate());
        }
        $this->_requestResource->save($rmaRequest);
        $this->_eventManager->dispatch(
            'mageplaza_rma_template_prepare_save',
            ['reply' => $reply, 'request' => $this->getRequest()]
        );
        try {
            if ($reply->getIsCustomerNotified()) {
                $this->_sendEmailToCustomer($reply, $rmaRequest, $messageBlock);
            }
            $this->_replyResource->save($reply);
            $messageBlock->addSuccess(__('You have sent the reply'));

            $result['status'] = true;
        } catch (Exception $e) {
            $messageBlock->addError(__($e->getMessage()));
            $result['status'] = false;
        }
        $result['message'] = $messageBlock->toHtml();
        /** @var Reply $conversationBlock */
        $conversationBlock = $this->_layout->createBlock(Reply::class);
        $conversationBlock->setTemplate('Mageplaza_RMA::request/form/reply/conversation.phtml');
        $replyCollection = $rmaRequest->getReplyCollection();
        $isLoadMore = false;
        if ($replyCollection->getSize() > 5) {
            $replyCollection->setPageSize(5);
            $isLoadMore = true;
        }
        $result['conversation'] = $conversationBlock
            ->setIsLoadMore($isLoadMore)
            ->setReplyCollection($replyCollection)
            ->toHtml();

        return $this->_resultJson->setData($result);
    }

    /**
     * @param ReplyModel $reply
     * @param array $data
     *
     * @return $this
     */
    protected function prepareData($reply, $data)
    {
        $data['type'] = (isset($data['is_visible_on_front']) || isset($data['is_customer_notified']))
            ? HelperConversation::TYPE_REPLY : HelperConversation::TYPE_NOTE;

        $reply->addData($data);

        return $this;
    }

    /**
     * Send email to customer when the reply is sent
     *
     * @param ReplyModel $reply
     * @param RequestModel $request
     * @param Messages $messageBlock
     *
     * @throws NoSuchEntityException
     */
    protected function _sendEmailToCustomer(&$reply, $request, &$messageBlock)
    {
        /** Send mail to customer when the question is answered */
        $toEmail = $request->getCustomerEmail();
        /** @var Order $order */
        $order = $request->getOrder();
        $emailTemplate = $this->_helperData->getEmailConfig('customer_template');
        $replyContent = strip_tags($reply->getContent());
        $replyContent = str_replace('&nbsp;', '', $replyContent);
        /** @var Store $currentStore */
        $currentStore = $this->_storeManager->getStore();
        $sender = $this->_helperData->getEmailConfig('sender');
        if ($toEmail && $this->_helperData->getEmailConfig('enabled')) {
            try {
                $requestUrl = $this->_url->getBaseUrl() . 'mprma/request/view/request_id/' . $request->getId();
                if ($request->getOrder()->getCustomerIsGuest()) {
                    $protectKey = $request->getId() . '_' . strtotime($request->getUpdatedAt());
                    $requestUrl .= '/guest_key/' . $this->_encryptor->hash($protectKey);
                }
                $vars = [
                    'customer_name' => $order->getCustomerName(),
                    'request_increment_id' => $request->getIncrementId(),
                    'request_url' => $requestUrl,
                    'request_content' => $replyContent,
                    'date' => $reply->getCreatedAt()
                ];
                $emailInfo = [
                    'current_store_id' => $currentStore->getId(),
                    'to_email' => $toEmail,
                    'email_template' => $emailTemplate,
                    'sender' => $sender
                ];
                $this->_helperData->sendMail($reply, $emailInfo, $vars);
            } catch (Exception $e) {
                $reply->setIsCustomerNotified(0);
                $messageBlock->addError($e->getMessage());
            }
        }
    }
}
