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

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Layout;
use Mageplaza\RMA\Block\Request\View\Reply;
use Mageplaza\RMA\Helper\Conversation;
use Mageplaza\Core\Helper\AbstractData as CoreHelper;

/**
 * Class Load
 * @package Mageplaza\RMA\Controller\Request\Reply
 */
class Load extends Action
{
    /**
     * @var Layout
     */
    protected $_layout;

    /**
     * @var Conversation
     */
    protected $_helperConversation;

    /**
     * @var CoreHelper
     */
    protected $_coreHelper;

    /**
     * Load constructor.
     *
     * @param Context $context
     * @param Layout $layout
     * @param Conversation $helperConversation
     * @param CoreHelper $coreHelper
     */
    public function __construct(
        Context $context,
        Layout $layout,
        Conversation $helperConversation,
        CoreHelper $coreHelper
    ) {
        $this->_layout = $layout;
        $this->_helperConversation = $helperConversation;
        $this->_coreHelper = $coreHelper;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        /** @var Http $request */
        $request = $this->getRequest();
        /** @var Reply $conversationBlock */
        $conversationBlock = $this->_layout->createBlock(Reply::class);
        if ($this->_coreHelper->checkHyvaTheme()) {
            $conversationBlock->setTemplate('Mageplaza_RMA::hyva/request/view/conversation.phtml');
        } else {
            $conversationBlock->setTemplate('Mageplaza_RMA::request/view/conversation.phtml');
        }

        return $this->_helperConversation->loadRequestReply($request, $conversationBlock, true);
    }
}
