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

namespace Mageplaza\RMA\Controller\Request;

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Mageplaza\RMA\Helper\Conversation;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\Request;
use Mageplaza\RMA\Model\Request\Reply;
use Mageplaza\RMA\Model\Request\ReplyFactory;
use Mageplaza\RMA\Model\RequestFactory;
use Mageplaza\RMA\Model\ResourceModel\Request as RequestResource;
use Mageplaza\RMA\Model\ResourceModel\Request\Reply as ReplyResource;

/**
 * Class Cancel
 * @package Mageplaza\RMA\Controller\Request
 */
class Cancel extends Action
{
    /**
     * @var ForwardFactory
     */
    protected $_resultForwardFactory;

    /**
     * @var RequestFactory
     */
    protected $_requestFactory;

    /**
     * @var RequestResource
     */
    protected $_requestResource;

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
     * Form constructor.
     *
     * @param Context $context
     * @param ForwardFactory $resultForwardFactory
     * @param RequestFactory $requestFactory
     * @param RequestResource $requestResource
     * @param ReplyFactory $replyFactory
     * @param ReplyResource $replyResource
     * @param HelperData $helperData
     */
    public function __construct(
        Context $context,
        ForwardFactory $resultForwardFactory,
        RequestFactory $requestFactory,
        RequestResource $requestResource,
        ReplyFactory $replyFactory,
        ReplyResource $replyResource,
        HelperData $helperData
    ) {
        $this->_resultForwardFactory = $resultForwardFactory;
        $this->_requestFactory = $requestFactory;
        $this->_requestResource = $requestResource;
        $this->_replyFactory = $replyFactory;
        $this->_replyResource = $replyResource;
        $this->_helperData = $helperData;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        if ($requestId = $this->_request->getParam('request_id')) {
            /** @var Request $request */
            $request = $this->_requestFactory->create();
            try {
                $this->_requestResource->load($request, $requestId);
                if (!$request->getId()
                    || !$this->_helperData->isLoggedIn()
                    || $request->getOrder()->getCustomerId() !== $this->_helperData->getCustomerId()) {
                    $this->messageManager->addErrorMessage(__('This request not found'));

                    return $this->resultRedirectFactory->create()->setPath('mprma/customer');
                }
                $request->setIsCanceled(1);
                $this->_requestResource->save($request);
                /** @var Reply $reply */
                $reply = $this->_replyFactory->create();
                $dataReply = [
                    'author_name' => __('Customer'),
                    'is_visible_on_front' => 1,
                    'type' => Conversation::TYPE_CUSTOMER_RESPONSE,
                    'content' => __('Customer has been canceled this request.'),
                    'request_id' => (int)$requestId
                ];
                $reply->addData($dataReply);
                $this->_replyResource->save($reply);

                $this->messageManager->addSuccessMessage(__('You have canceled this request'));
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }

            return $this->resultRedirectFactory->create()->setPath('mprma/customer');
        }

        return $this->_resultForwardFactory->create()->forward('noroute');
    }
}
