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

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Layout;
use Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form\Reply;
use Mageplaza\RMA\Helper\Conversation;

/**
 * Class Load
 * @package Mageplaza\RMA\Controller\Adminhtml\Request\Reply
 */
class Load extends Action
{
    /** Authorization level of a basic admin session */
    const ADMIN_RESOURCE = 'Mageplaza_RMA::request';

    /**
     * @var Layout
     */
    protected $_layout;

    /**
     * @var Conversation
     */
    protected $_helperConversation;

    /**
     * Save constructor.
     *
     * @param Context $context
     * @param Layout $layout
     * @param Conversation $helperConversation
     */
    public function __construct(
        Context $context,
        Layout $layout,
        Conversation $helperConversation
    ) {
        $this->_layout = $layout;
        $this->_helperConversation = $helperConversation;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        /** @var Http $request */
        $request = $this->getRequest();
        /** @var Reply $conversationBlock */
        $conversationBlock = $this->_layout->createBlock(Reply::class);
        $conversationBlock->setTemplate('Mageplaza_RMA::request/form/reply/conversation.phtml');

        return $this->_helperConversation->loadRequestReply($request, $conversationBlock);
    }
}
