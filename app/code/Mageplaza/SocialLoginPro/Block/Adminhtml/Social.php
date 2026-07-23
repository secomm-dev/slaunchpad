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
 * @package   Mageplaza_SocialLoginPro
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\SocialLoginPro\Block\Adminhtml;

use Magento\Backend\Model\Auth\Session;
use Magento\Framework\Url;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mageplaza\SocialLogin\Helper\Social as SocialHelper;
use Mageplaza\SocialLogin\Model\SocialFactory;

/**
 * Class Social
 *
 * @package Mageplaza\SocialLoginPro\Block\Adminhtml
 */
class Social extends Template
{
    /**
     * @var Url
     */
    protected $_frontendUrl;

    /**
     * @var Session
     */
    protected $_authSession;

    /**
     * @var SocialFactory
     */
    protected $_socialModel;

    /**
     * @var SocialHelper
     */
    protected $socialHelper;

    /**
     * Social constructor.
     *
     * @param SocialHelper $socialHelper
     * @param Context $context
     * @param Session $authSession
     * @param Url $frontendUrl
     * @param SocialFactory $socialFactory
     * @param array $data
     */
    public function __construct(
        SocialHelper $socialHelper,
        Context $context,
        Session $authSession,
        Url $frontendUrl,
        SocialFactory $socialFactory,
        array $data = []
    ) {
        $this->socialHelper = $socialHelper;
        $this->_authSession = $authSession;
        $this->_frontendUrl = $frontendUrl;
        $this->_socialModel = $socialFactory;
        parent::__construct($context, $data);
    }

    /**
     * @return array
     */
    public function getAvailableSocials()
    {
        $availableSocials = [];
        $param            = [
            '_nosid'  => true,
            '_secure' => true,
        ];

        if ($this->_authSession->isLoggedIn()) {
            $param = array_merge($param, ['connect' => true]);
        }

        foreach ($this->socialHelper->getSocialTypes() as $socialKey => $socialLabel) {
            $this->socialHelper->setType($socialKey);
            if ($this->socialHelper->isEnabled() && $this->socialHelper->isSignInAsAdmin()) {
                $availableSocials[$socialKey] = [
                    'label'          => $socialLabel,
                    'login_url'      => $this->getLoginUrl($socialKey, $param),
                    'disconnect_url' => $this->getDisconnectUrl(),
                    'connected'      => $this->isConnected($socialLabel),
                    'id'             => $this->getUserId(),
                    'pd_url'         => $this->getProcessDataUrl()
                ];
            }
        }

        return $availableSocials;
    }

    /**
     * @return string
     */
    public function getDisconnectUrl()
    {
        return $this->getUrl('sociallogin/social/disconnect');
    }

    /**
     * @param $type
     *
     * @return string
     */
    public function isConnected($type)
    {
        if ($type && $this->_authSession->isLoggedIn()) {
            $userId     = $this->_authSession->getUser()->getId();
            $social     = $this->_socialModel->create();
            $collection = $social->getCollection()
                ->addFieldToFilter('type', $type)
                ->addFieldToFilter('user_id', $userId)
                ->addFieldToFilter('status', $social::STATUS_CONNECT);

            if ($collection->getSize() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $key
     *
     * @return string
     */
    public function getBtnKey($key)
    {
        return $key === 'vkontakte' ? 'vk' : $key;
    }

    /**
     * @param $socialKey
     * @param array $param
     *
     * @return string
     */
    public function getLoginUrl($socialKey, $param = [])
    {
        return $this->_frontendUrl->getUrl(
            'sociallogin/auth/login/type/' . $socialKey,
            $param
        );
    }

    /**
     * @return mixed|null
     */
    public function getUserId()
    {
        if ($this->_authSession->isLoggedIn()) {
            return $this->_authSession->getUser()->getId();
        }

        return null;
    }

    /**
     * @return string
     */
    public function getProcessDataUrl()
    {
        return $this->getUrl('sociallogin/social/processdata');
    }
}
