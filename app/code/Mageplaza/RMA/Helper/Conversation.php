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

namespace Mageplaza\RMA\Helper;

use Exception;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\Messages;
use Magento\Framework\View\Layout;
use Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form\Reply as BackendReply;
use Mageplaza\RMA\Block\Request\View\Reply as FrontendReply;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Helper\Image as HelperImage;
use Mageplaza\RMA\Model\Request;
use Mageplaza\RMA\Model\Request\Reply as ReplyModel;
use Mageplaza\RMA\Model\RequestFactory;
use Mageplaza\RMA\Model\ResourceModel\Request as RequestResource;

/**
 * Class Conversation
 * @package Mageplaza\RMA\Helper
 */
class Conversation extends AbstractHelper
{
    const TYPE_REPLY = 1;
    const TYPE_CUSTOMER_RESPONSE = 2;
    const TYPE_NOTE = 3;

    /**
     * @var Escaper
     */
    protected $_escaper;

    /**
     * @var Json
     */
    protected $_resultJson;

    /**
     * @var ForwardFactory
     */
    protected $_resultFwFactory;

    /**
     * @var Layout
     */
    protected $_layout;

    /**
     * @var RequestFactory
     */
    protected $_requestFactory;

    /**
     * @var RequestResource
     */
    protected $_requestResource;

    /**
     * @var Data
     */
    protected $_helperData;

    /**
     * @var Image
     */
    protected $_helperImage;

    /**
     * Conversation constructor.
     *
     * @param Context $context
     * @param Escaper $escaper
     * @param Json $resultJson
     * @param ForwardFactory $resultFwFactory
     * @param Layout $layout
     * @param RequestFactory $requestFactory
     * @param RequestResource $requestResource
     * @param Data $helperData
     * @param Image $helperImage
     */
    public function __construct(
        Context $context,
        Escaper $escaper,
        Json $resultJson,
        ForwardFactory $resultFwFactory,
        Layout $layout,
        RequestFactory $requestFactory,
        RequestResource $requestResource,
        HelperData $helperData,
        HelperImage $helperImage
    ) {
        $this->_escaper = $escaper;
        $this->_resultJson = $resultJson;
        $this->_resultFwFactory = $resultFwFactory;
        $this->_layout = $layout;
        $this->_requestFactory = $requestFactory;
        $this->_requestResource = $requestResource;
        $this->_helperData = $helperData;
        $this->_helperImage = $helperImage;

        parent::__construct($context);
    }

    /**
     * @param ReplyModel $reply
     *
     * @return string
     * @throws Exception
     */
    public function getMessageHeader($reply)
    {
        $timeAgo = $this->_helperData->getTimeAgo($reply->getCreatedAt());
        $createDate = $this->_helperData->getConvertedDate($reply->getCreatedAt());
        $date = $createDate->format('D, d M Y');
        $time = $createDate->format('h:i A');

        $message = (int)$reply->getType() === self::TYPE_NOTE
            ? __('added a private note, %1 (%2 at %3)', $timeAgo, $date, $time)
            : __('replied, %1 (%2 at %3)', $timeAgo, $date, $time);

        if ($reply->getIsCustomerNotified()) {
            $message .= '<i class="fa fa-envelope" aria-hidden="true"></i>';
        }

        return $message;
    }

    /**
     * @param int $type
     *
     * @return string
     */
    public function getConversationTypeClass($type)
    {
        switch ($type) {
            case self::TYPE_NOTE:
                $class = 'mp-note';
                break;
            case self::TYPE_REPLY:
                $class = 'mp-reply';
                break;
            case self::TYPE_CUSTOMER_RESPONSE:
                $class = 'mp-customer';
                break;
            default:
                $class = '';
        }

        return $class;
    }

    /**
     * @param string $content
     *
     * @return string
     */
    public function formatReplyContent($content)
    {
        if (is_string($content)) {
            $html = '';
            foreach (explode("\n", trim($content)) as $value) {
                $html .= '<p>' . $this->_escaper->escapeHtml($value) . '</p>';
            }

            return $html;
        }

        return $content;
    }

    /**
     * @param array $file
     * @param ReplyModel $reply
     *
     * @return string
     */
    public function getReplyFileJson($file, $reply)
    {
        $fileInfo = [
            'file_id' => $file['file_id'],
            'reply_id' => $reply->getId()
        ];

        return HelperData::jsonEncode($fileInfo);
    }

    /**
     * @param string $files
     *
     * @return array
     */
    public function getReplyFiles($files)
    {
        return HelperData::jsonDecode($files);
    }

    /**
     * @param Http $request
     * @param FrontendReply|BackendReply $conversationBlock
     * @param bool $isFront
     *
     * @return Forward|Json
     */
    public function loadRequestReply($request, $conversationBlock, $isFront = false)
    {
        if (!$request->isAjax()) {
            return $this->_resultFwFactory->create()->forward('noroute');
        }
        if (!$requestId = $request->getPost('request_id')) {
            return $this->_resultJson->setData(
                ['requestRedirect' => $this->_urlBuilder->getUrl('mprma/request/index')]
            );
        }
        $loadAll = $request->getParam('load_all', false);
        $customerEmail = $request->getParam('customer_email', '');
        /** @var Messages $messageBlock */
        $messageBlock = $this->_layout->createBlock(Messages::class);
        /** @var Request $rmaRequest */
        $rmaRequest = $this->_requestFactory->create();
        $this->_requestResource->load($rmaRequest, $requestId);
        if (!$rmaRequest->getData() || ($isFront && $rmaRequest->getCustomerEmail() !== $customerEmail)) {
            $messageBlock->addError(__('Your request information is incorrect.'));
            $result = [
                'status' => false,
                'conversation' => $messageBlock->toHtml()
            ];

            return $this->_resultJson->setData($result);
        }
        $replyCollection = $rmaRequest->getReplyCollection($isFront);
        $showLoadMore = false;
        if (!$loadAll && $replyCollection->getSize() > 5) {
            $showLoadMore = true;
            $replyCollection->setPageSize(5);
        }
        $conversationBlock->setIsLoadMore($showLoadMore)
            ->setReplyCollection($replyCollection);
        $result = [
            'status' => true,
            'conversation' => $conversationBlock->toHtml()
        ];

        return $this->_resultJson->setData($result);
    }
}
