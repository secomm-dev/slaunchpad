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

namespace Mageplaza\SocialLoginPro\Plugin\Controller\Adminhtml\Auth;

use Exception;
use Magento\Backend\Controller\Adminhtml\Auth\Login;
use Magento\Backend\Model\AuthFactory;
use Magento\Backend\Model\Url;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManager;
use Magento\Security\Model\AdminSessionsManager;
use Magento\User\Model\UserFactory;
use Mageplaza\SocialLogin\Helper\Data as HelperData;
use Mageplaza\SocialLogin\Model\SocialFactory;

/**
 * Class SocialLogin
 *
 * @package Mageplaza\SocialLoginPro\Plugin\Controller\Adminhtml\Auth
 */
class SocialLogin
{
    /**
     * @var AuthFactory
     */
    protected $_auth;

    /**
     * @var AdminSessionsManager
     */
    protected $_sessionsManager;

    /**
     * @var Http
     */
    protected $_request;

    /**
     * @var RawFactory
     */
    protected $resultRawFactory;

    /**
     * @var SocialFactory
     */
    protected $_socialModel;

    /**
     * @var ManagerInterface
     */
    protected $_messageManager;

    /**
     * @var Url
     */
    protected $_backendUrl;

    /**
     * @var UserFactory
     */
    protected $_userCollection;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * @var SessionManager
     */
    protected $_storageSession;

    /**
     * SocialLogin constructor.
     *
     * @param AuthFactory $authFactory
     * @param AdminSessionsManager $adminSessionsManager
     * @param Http $request
     * @param RawFactory $resultRawFactory
     * @param SocialFactory $socialFactory
     * @param ManagerInterface $messageManager
     * @param UserFactory $userFactory
     * @param HelperData $helperData
     * @param Url $backendUrl
     * @param SessionManager $sessionManager
     */
    public function __construct(
        AuthFactory $authFactory,
        AdminSessionsManager $adminSessionsManager,
        Http $request,
        RawFactory $resultRawFactory,
        SocialFactory $socialFactory,
        ManagerInterface $messageManager,
        UserFactory $userFactory,
        HelperData $helperData,
        Url $backendUrl,
        SessionManager $sessionManager
    ) {
        $this->_auth            = $authFactory;
        $this->_sessionsManager = $adminSessionsManager;
        $this->_request         = $request;
        $this->resultRawFactory = $resultRawFactory;
        $this->_socialModel     = $socialFactory;
        $this->_messageManager  = $messageManager;
        $this->_userCollection  = $userFactory;
        $this->_helperData      = $helperData;
        $this->_backendUrl      = $backendUrl;
        $this->_storageSession  = $sessionManager;
    }

    /**
     * @param Login $subject
     * @param $result
     *
     * @return Raw
     */
    public function afterExecute(Login $subject, $result)
    {
        if ($this->_helperData->isEnabled()) {
            $params   = $this->_request->getParams();
            $errorMsg = 'You have not connected this social account to sign in. Please set the connection first.';

            if (isset($params['denied'])) {
                $this->_messageManager->addErrorMessage(__($errorMsg));

                return $this->_appendJs($this->_backendUrl->getUrl('admin/index'));
            }

            if (isset($params['type'])) {
                try {
                    $socialCollection = $this->_socialModel->create();
                    $social           = $socialCollection->getUser($params['type'], $params['id']);
                    $user             = $this->_userCollection->create()->load($social->getUserId());
                    $socialCollection->updateStatus($social->getSocialCustomerId(), $socialCollection::STATUS_CONNECT);

                    if ($this->_helperData->isModuleOutputEnabled('Mageplaza_TwoFactorAuth')
                        && $this->_helperData->getConfigValue('mptwofactorauth/general/enabled', null)
                        && $this->_helperData->getConfigValue('mptwofactorauth/general/force_2fa', null)
                    ) {
                        $url = $this->_backendUrl->getUrl('mptwofactorauth/google/authindex/');
                        $this->_storageSession->setData('user', $user);

                        return $this->_appendJs($url);
                    }

                    if (empty($social->getData())) {
                        $this->_messageManager->addErrorMessage(__($errorMsg));

                        return $this->_appendJs($this->_backendUrl->getUrl('admin/index'));
                    }

                    $auth = $this->_auth->create();
                    $auth->getAuthStorage()->setUser($user);
                    $auth->getAuthStorage()->processLogin();
                    $this->_sessionsManager->processLogin();
                } catch (Exception $e) {
                    $this->_messageManager->addErrorMessage(__($e->getMessage()));
                }

                return $this->_appendJs($this->_backendUrl->getUrl('admin/dashboard'));
            }
        }

        return $result;
    }

    /**
     * @param null $url
     *
     * @return Raw
     */
    public function _appendJs($url = null)
    {
        /**
         * @var Raw $resultRaw
         */
        $resultRaw = $this->resultRawFactory->create();

        return $resultRaw->setContents(
            sprintf(
                "<script>
                window.opener.location.href = '%s';
                window.close()
            </script>",
                $url
            )
        );
    }
}
