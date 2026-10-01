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
 * @category  Mageplaza
 * @package   Mageplaza_RMA
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Controller\Request\Reply;

use Exception;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Messages;
use Magento\Framework\View\Layout;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\RMA\Block\Request\View\Reply;
use Mageplaza\RMA\Helper\Conversation as HelperConversation;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Helper\Image;
use Mageplaza\RMA\Model\Config\Source\System\Email\NotifyType;
use Mageplaza\RMA\Model\Request as RequestModel;
use Mageplaza\RMA\Model\Request\Reply as ReplyModel;
use Mageplaza\RMA\Model\Request\ReplyFactory;
use Mageplaza\RMA\Model\RequestFactory;
use Mageplaza\RMA\Model\ResourceModel\Request as RequestResource;
use Mageplaza\RMA\Model\ResourceModel\Request\Reply as ReplyResource;
use Psr\Log\LoggerInterface;

/**
 * Class Save
 * @package Mageplaza\RMA\Controller\Request\Reply
 */
class Save extends Action
{
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
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @var Session
     */
    protected $_customerSession;

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
     * @param LoggerInterface $logger
     * @param Session $customerSession
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
        LoggerInterface $logger,
        Session $customerSession,
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
        $this->_logger = $logger;
        $this->_customerSession = $customerSession;
        $this->_replyFactory = $replyFactory;
        $this->_replyResource = $replyResource;
        $this->_requestFactory = $requestFactory;
        $this->_requestResource = $requestResource;
        $this->_helperImage = $helperImage;
        $this->_helperData = $helperData;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     * @throws AlreadyExistsException
     * @throws LocalizedException
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
        if (!$data = $request->getPost('reply')) {
            $messageBlock->addError(__('Cannot find request reply data.'));
            $result = [
                'status' => false,
                'message' => $messageBlock->toHtml()
            ];

            return $this->_resultJson->setData($result);
        }
        $data = $request->getPost('reply');
        /** @var ReplyModel $reply */
        $reply = $this->_replyFactory->create();
        /**
         * Save request last responded name
         * @var RequestModel $rmaRequest
         */
        $rmaRequest = $this->_requestFactory->create();
        $this->_requestResource->load($rmaRequest, (int)$data['request_id']);
        if (!$rmaRequest->getData() || $rmaRequest->getCustomerEmail() !== $data['author_email']) {
            $messageBlock->addError(__('Your request information is incorrect.'));
            $result = [
                'status' => false,
                'message' => $messageBlock->toHtml()
            ];

            return $this->_resultJson->setData($result);
        }
        $respondedBy = $data['author_name'] . ' (' . $data['author_email'] . ')';
        $rmaRequest->setLastRespondedBy($respondedBy);
        $this->_requestResource->save($rmaRequest);
        /** Upload files */
        if (isset($data['files']) && count($data['files'])) {
            $data['files'] = HelperData::jsonEncode($this->_helperImage->processImagesGallery($data['files']));
        }
        $this->prepareData($reply, $data);

        $this->_eventManager->dispatch(
            'mageplaza_rma_template_prepare_save',
            ['reply' => $reply, 'request' => $this->getRequest()]
        );
        try {
            $this->_sendEmailToAdmin($reply, $rmaRequest);
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
        if ($this->_helperData->checkHyvaTheme()) {
            $conversationBlock->setTemplate('Mageplaza_RMA::hyva/request/view/conversation.phtml');
        } else {
            $conversationBlock->setTemplate('Mageplaza_RMA::request/view/conversation.phtml');
        }
        $replyCollection = $rmaRequest->getReplyCollection(true);
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
        $data['type'] = HelperConversation::TYPE_CUSTOMER_RESPONSE;

        $data['is_visible_on_front'] = 1;
        $reply->addData($data);

        return $this;
    }

    /**
     * Send email to admin when the reply is sent
     *
     * @param ReplyModel $reply
     * @param RequestModel $request
     *
     * @throws NoSuchEntityException
     */
    protected function _sendEmailToAdmin($reply, $request)
    {
        if ($this->_helperData->getEmailConfig('is_notify')) {
            foreach ($this->_getAdminEmails($reply) as $toEmail) {
                $emailTemplate = $this->_helperData->getEmailConfig('admin_template');
                $replyContent = strip_tags($reply->getContent());
                $replyContent = str_replace('&nbsp;', '', $replyContent);
                /** @var Store $currentStore */
                $currentStore = $this->_storeManager->getStore();
                $sender = $this->_helperData->getEmailConfig('sender');
                if ($toEmail && $this->_helperData->getEmailConfig('enabled')) {
                    try {
                        $vars = [
                            'request_increment_id' => $request->getIncrementId(),
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
                        $this->_logger->critical($e);
                    }
                }
            }
        }
    }

    /**
     * @param ReplyModel $reply
     *
     * @return array|bool
     */
    protected function _getAdminEmails($reply)
    {
        $sendEmailType = $this->_helperData->getEmailConfig('is_notify');
        if (!$sendEmailType) {
            return false;
        }
        $adminEmails = '';
        if ((int)$sendEmailType !== NotifyType::NO) {
            $adminEmails = $this->_helperData->getEmailConfig('admin_emails');
            $adminEmails = preg_replace('/\s+/', '', explode(',', $adminEmails ?: ''));
        }

        return $adminEmails;
    }
}
