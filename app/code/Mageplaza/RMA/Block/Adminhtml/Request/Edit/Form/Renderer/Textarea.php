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

namespace Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form\Renderer;

use Magento\Backend\Model\Auth;
use Magento\Framework\Data\Form\Element\CollectionFactory;
use Magento\Framework\Data\Form\Element\Factory;
use Magento\Framework\Data\Form\Element\Textarea as TextareaElement;
use Magento\Framework\Escaper;
use Magento\User\Model\User;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\RMA\Model\Config\Source\System\Request\ReplyName;

/**
 * Class Textarea
 * @package Mageplaza\RMA\Block\Adminhtml\Request\Edit\Form\Renderer
 */
class Textarea extends TextareaElement
{
    /**
     * @var Auth
     */
    protected $_auth;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * Textarea constructor.
     *
     * @param Factory $factoryElement
     * @param CollectionFactory $factoryCollection
     * @param Escaper $escaper
     * @param Auth $auth
     * @param HelperData $helperData
     * @param array $data
     */
    public function __construct(
        Factory $factoryElement,
        CollectionFactory $factoryCollection,
        Escaper $escaper,
        Auth $auth,
        HelperData $helperData,
        $data = []
    ) {
        $this->_auth = $auth;
        $this->_helperData = $helperData;

        parent::__construct(
            $factoryElement,
            $factoryCollection,
            $escaper,
            $data
        );
    }

    /**
     * Return the element as HTML
     *
     * @return string
     */
    public function getElementHtml()
    {
        $authorHtml = '<div class="mp-reply-author"><span>' . __('From: ') . '<b>' . $this->_getReplyArgentName() . '</b></span></div>';
        $authorHtml .= '<input type="hidden" id="mp-reply-name" name="reply[author_name]"
        value="' . $this->_getReplyArgentName() . '">';
        $authorHtml .= '<input type="hidden" id="mp-reply-email" name="reply[author_email]"
        value="' . $this->_getReplyArgentEmail() . '">';

        return $authorHtml . parent::getElementHtml();
    }

    /**
     * @return string
     */
    protected function _getReplyArgentName()
    {
        if ((int)$this->_helperData->getRequestConfig('reply_name') === ReplyName::DEFAULT_NAME) {
            return $this->_helperData->getRequestConfig('default_name');
        }

        return $this->_getCurrentUser()->getName();
    }

    /**
     * @return mixed|string
     */
    protected function _getReplyArgentEmail()
    {
        return $this->_getCurrentUser()->getEmail();
    }

    /**
     * @return Auth\Credential\StorageInterface|User
     */
    protected function _getCurrentUser()
    {
        return $this->_auth->getUser();
    }
}
