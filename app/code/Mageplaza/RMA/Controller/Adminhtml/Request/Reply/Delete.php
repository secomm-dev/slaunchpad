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
use Magento\Framework\View\Element\Messages;
use Magento\Framework\View\Layout;
use Mageplaza\RMA\Model\Request\Reply as ReplyModel;
use Mageplaza\RMA\Model\Request\ReplyFactory;
use Mageplaza\RMA\Model\ResourceModel\Request\Reply as ReplyResource;

/**
 * Class Delete
 * @package Mageplaza\RMA\Controller\Adminhtml\Request\Reply
 */
class Delete extends Action
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
     * @var ReplyFactory
     */
    protected $_replyFactory;

    /**
     * @var ReplyResource
     */
    protected $_replyResource;

    /**
     * Save constructor.
     *
     * @param Context $context
     * @param ForwardFactory $resultFwFactory
     * @param Layout $layout
     * @param Json $resultJson
     * @param ReplyFactory $replyFactory
     * @param ReplyResource $replyResource
     */
    public function __construct(
        Context $context,
        ForwardFactory $resultFwFactory,
        Layout $layout,
        Json $resultJson,
        ReplyFactory $replyFactory,
        ReplyResource $replyResource
    ) {
        $this->_resultFwFactory = $resultFwFactory;
        $this->_layout = $layout;
        $this->_resultJson = $resultJson;
        $this->_replyFactory = $replyFactory;
        $this->_replyResource = $replyResource;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
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
        if (!$replyId = $request->getPost('reply_id')) {
            $messageBlock->addError(__('This reply not found.'));
            $result = [
                'status' => true,
                'message' => $messageBlock->toHtml()
            ];

            return $this->_resultJson->setData($result);
        }
        $replyId = (int)$request->getPost('reply_id');
        /** @var ReplyModel $reply */
        $reply = $this->_replyFactory->create();
        $this->_replyResource->load($reply, $replyId);
        if ($reply->getId()) {
            try {
                $this->_replyResource->delete($reply);
                $messageBlock->addSuccess(__('You have delete the reply.'));
            } catch (Exception $e) {
                $messageBlock->addError($e->getMessage());
            }
        } else {
            $messageBlock->addError(__('This reply not found.'));
        }

        $result = [
            'status' => true,
            'message' => $messageBlock->toHtml()
        ];

        return $this->_resultJson->setData($result);
    }
}
