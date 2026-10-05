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

namespace Mageplaza\RMA\Block\Request\View;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mageplaza\RMA\Helper\Conversation;
use Mageplaza\RMA\Model\ResourceModel\Request\Reply\Collection;

/**
 * Class Reply
 * @method bool getIsLoadMore()
 * @method Collection getReplyCollection()
 * @method Reply setIsLoadMore(bool $flag)
 * @method Reply setReplyCollection(Collection $replyCollection)
 * @package Mageplaza\RMA\Block\Request\View
 */
class Reply extends Template
{
    /**
     * @var Conversation
     */
    public $helperConversation;

    /**
     * Reply constructor.
     *
     * @param Context $context
     * @param Conversation $helperConversation
     * @param array $data
     */
    public function __construct(
        Context $context,
        Conversation $helperConversation,
        array $data = []
    ) {
        $this->helperConversation = $helperConversation;

        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getDownloadFileUrl()
    {
        return $this->getUrl('mprma/request/file_download');
    }

    /**
     * @return string
     */
    public function getLoadReplyUrl()
    {
        return $this->getUrl('mprma/request/reply_load');
    }

    /**
     * @return string
     */
    public function getDeleteReplyUrl()
    {
        return $this->getUrl('mprma/request/reply_delete');
    }
}
