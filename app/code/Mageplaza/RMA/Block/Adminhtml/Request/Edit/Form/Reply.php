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

namespace Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form;

use Magento\Backend\Block\Template\Context;
use Mageplaza\RMA\Block\Adminhtml\Request\Edit\Request;
use Mageplaza\RMA\Helper\Conversation;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\ResourceModel\Request\Reply\Collection;

/**
 * Class Reply
 * @method bool getIsLoadMore()
 * @method Collection getReplyCollection()
 * @method Reply setIsLoadMore(bool $flag)
 * @method Reply setId($id)
 * @method Reply setElement($element)
 * @method Reply setFormName($formName)
 * @method Reply setReplyCollection(Collection $replyCollection)
 * @package Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form
 */
class Reply extends Request
{
    /**
     * @var Conversation
     */
    public $helperConversation;

    /**
     * @var HelperData
     */
    public $helperData;

    /**
     * Reply constructor.
     *
     * @param Context $context
     * @param Conversation $helperConversation
     * @param HelperData $helperData
     * @param array $data
     */
    public function __construct(
        Context $context,
        Conversation $helperConversation,
        HelperData $helperData,
        array $data = []
    ) {
        $this->helperConversation = $helperConversation;
        $this->helperData = $helperData;

        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getUploadUrl()
    {
        return $this->getUrl('mprma/request/reply_upload', ['form_key' => $this->getFormKey()]);
    }

    /**
     * @return string
     */
    public function getLoadReplyUrl()
    {
        return $this->getUrl('mprma/request/reply_load', ['form_key' => $this->getFormKey()]);
    }

    /**
     * @return string
     */
    public function getSaveReplyUrl()
    {
        return $this->getUrl('mprma/request/reply_save', ['form_key' => $this->getFormKey()]);
    }

    /**
     * @return string
     */
    public function getDeleteReplyUrl()
    {
        return $this->getUrl('mprma/request/reply_delete', ['form_key' => $this->getFormKey()]);
    }

    /**
     * @return string
     */
    public function getDownloadFileUrl()
    {
        return $this->getUrl('mprma/request/file_download', ['form_key' => $this->getFormKey()]);
    }
}
