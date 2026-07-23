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

namespace Mageplaza\SocialLoginPro\Controller\Popup;

use Exception;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Json\Helper\Data as JsonData;
use Mageplaza\SocialLoginPro\Helper\Data;

/**
 * Class Login
 *
 * @package Mageplaza\SocialLoginPro\Controller\Popup
 */
class Login extends Action
{
    /**
     * @var AccountManagementInterface
     */
    protected $customerAccountManagement;

    /**
     * @var JsonData $helper
     */
    protected $helper;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var RawFactory
     */
    protected $resultRawFactory;

    /**
     * @var AccountRedirect
     */
    protected $accountRedirect;

    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * @var Data
     */
    protected $slHelper;

    /**
     * Login constructor.
     *
     * @param Context $context
     * @param Session $customerSession
     * @param JsonData $helper
     * @param AccountManagementInterface $customerAccountManagement
     * @param JsonFactory $resultJsonFactory
     * @param RawFactory $resultRawFactory
     * @param Data $slHelper
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        JsonData $helper,
        AccountManagementInterface $customerAccountManagement,
        JsonFactory $resultJsonFactory,
        RawFactory $resultRawFactory,
        Data $slHelper
    ) {
        $this->customerSession           = $customerSession;
        $this->helper                    = $helper;
        $this->customerAccountManagement = $customerAccountManagement;
        $this->resultJsonFactory         = $resultJsonFactory;
        $this->resultRawFactory          = $resultRawFactory;
        $this->slHelper                  = $slHelper;

        parent::__construct($context);
    }

    /**
     * @return $this|ResponseInterface|ResultInterface
     */
    public function execute()
    {
        /**
         * @var Json $resultJson
         */
        $resultJson = $this->resultJsonFactory->create();

        if ($this->slHelper->isGoogleCaptcha()
            && in_array('user_login', (array) $this->slHelper->getRecaptchaForms(), true)
        ) {
            $credentials = $this->helper->jsonDecode($this->getRequest()->getContent());
            $response    = $this->slHelper->verifyResponse($credentials['g-recaptcha-response']);
            if (isset($response['success']) && !$response['success']) {
                $result = [
                    'errors'  => true,
                    'message' => $response['message']
                ];

                return $resultJson->setData($result);
            }
        }

        $credentials        = null;
        $httpBadRequestCode = 400;

        /**
         * @var Raw $resultRaw
         */
        $resultRaw = $this->resultRawFactory->create();
        try {
            $credentials = $this->helper->jsonDecode($this->getRequest()->getContent());
        } catch (Exception $e) {
            return $resultRaw->setHttpResponseCode($httpBadRequestCode);
        }
        if (!$credentials || $this->getRequest()->getMethod() !== 'POST' || !$this->getRequest()->isXmlHttpRequest()) {
            return $resultRaw->setHttpResponseCode($httpBadRequestCode);
        }

        $response = [
            'errors'  => false,
            'message' => __('Login successful.')
        ];
        try {
            $customer = $this->customerAccountManagement->authenticate(
                $credentials['username'],
                $credentials['password']
            );
            $this->customerSession->setCustomerDataAsLoggedIn($customer);
            $this->customerSession->regenerateId();
            $redirectRoute = $this->getAccountRedirect()->getRedirectCookie();
            if ($redirectRoute && !$this->slHelper->getConfigValue('customer/startup/redirect_dashboard')) {
                $response['redirectUrl'] = $this->_redirect->success($redirectRoute);
                $this->getAccountRedirect()->clearRedirectCookie();
            }
        } catch (LocalizedException $e) {
            $response = [
                'errors'  => true,
                'message' => $e->getMessage()
            ];
        } catch (Exception $e) {
            $response = [
                'errors'  => true,
                'message' => __('Invalid login or password.')
            ];
        }
        /**
         * @var Json $resultJson
         */
        $resultJson = $this->resultJsonFactory->create();

        return $resultJson->setData($response);
    }

    /**
     * @return AccountRedirect|mixed
     */
    protected function getAccountRedirect()
    {
        if (!is_object($this->accountRedirect)) {
            $this->accountRedirect = ObjectManager::getInstance()->get(AccountRedirect::class);
        }

        return $this->accountRedirect;
    }
}
