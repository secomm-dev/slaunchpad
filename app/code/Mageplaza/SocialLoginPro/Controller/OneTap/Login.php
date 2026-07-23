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
namespace Mageplaza\SocialLoginPro\Controller\OneTap;

use Laminas\Http\Request;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\SocialLogin\Controller\Social\AbstractSocial;
use Mageplaza\SocialLogin\Helper\Social as SocialHelper;
use Mageplaza\SocialLogin\Model\Social;
use Mageplaza\SocialLoginPro\Helper\Data;
use Magento\Integration\Model\Oauth\TokenFactory;
use Magento\Framework\Escaper;

/**
 * Class Login
 * @package Mageplaza\SocialLoginPro\Controller\OneTap
 */
class Login extends AbstractSocial
{
    const GOOGLE_APIS = 'https://oauth2.googleapis.com/tokeninfo?id_token=';

    /**
     * @var CurlFactory
     */
    protected $curlFactory;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var TokenFactory
     */
    protected $tokenFactory;

    /**
     * Login constructor.
     *
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param AccountManagementInterface $accountManager
     * @param SocialHelper $apiHelper
     * @param Social $apiObject
     * @param Session $customerSession
     * @param AccountRedirect $accountRedirect
     * @param RawFactory $resultRawFactory
     * @param Customer $customerModel
     * @param CurlFactory $curlFactory
     * @param Data $helperData
     * @param TokenFactory $tokenFactory
     * @param Escaper $escaper
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        AccountManagementInterface $accountManager,
        SocialHelper $apiHelper,
        Social $apiObject,
        Session $customerSession,
        AccountRedirect $accountRedirect,
        RawFactory $resultRawFactory,
        Customer $customerModel,
        CurlFactory $curlFactory,
        Data $helperData,
        TokenFactory $tokenFactory,
        Escaper $escaper
    ) {
        $this->curlFactory = $curlFactory;
        $this->helperData  = $helperData;

        parent::__construct(
            $context,
            $storeManager,
            $accountManager,
            $apiHelper,
            $apiObject,
            $customerSession,
            $accountRedirect,
            $resultRawFactory,
            $customerModel,
            $tokenFactory,
            $escaper
        );
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws FailureToSendException
     */
    public function execute()
    {
        $token       = $this->getRequest()->getParam('credential');
        $info        = $this->processCurl($token);
        $userProfile = $this->processData($info);
        $customer    = $this->apiObject->getCustomerBySocial($userProfile->identifier, 'Google');

        if (!$customer->getId()) {
            $requiredMoreInfo = (int) $this->apiHelper->requiredMoreInfo();

            if ((!$userProfile->email && $requiredMoreInfo === 2) || $requiredMoreInfo === 1) {
                $this->session->setUserProfile($userProfile);
            }

            $customer = $this->createCustomerProcess($userProfile, 'Google');
        }

        $this->refresh($customer);
        $url = $this->helperData->getRedirectUrl($this->helperData->getStoreId());
        if (!$url) {
            return $this->_redirect('');
        }

        $redirect = $this->resultRedirectFactory->create();
        $redirect->setUrl($url);

        return $redirect;
    }

    /**
     * @param string $token
     *
     * @return array|mixed
     */
    public function processCurl($token)
    {
        $httpAdapter = $this->curlFactory->create();
        $httpAdapter->write(Request::METHOD_POST, self::GOOGLE_APIS . $token);
        $result   = $httpAdapter->read();
        $response = Data::extractBody($result);
        $response = Data::jsonDecode($response);
        $httpAdapter->close();

        return $response;
    }

    /**
     * @param array $info
     *
     * @return DataObject
     */
    public function processData(array $info)
    {
        $obj              = new DataObject();
        $obj->identifier  = $info['sub'];
        $obj->displayName = $info['name'];
        $obj->firstName   = $info['given_name'];
        $obj->lastName    = isset($info['family_name']) ?: '';
        $obj->email       = $info['email'];

        return $obj;
    }
}
