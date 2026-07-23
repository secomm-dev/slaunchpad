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

namespace Mageplaza\SocialLoginPro\Helper;

use Exception;
use Laminas\Http\Request;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\SocialLogin\Helper\Social as StandardHelper;
use Mageplaza\SocialLoginPro\Model\Config\Source\Captcha;
use Mageplaza\SocialLoginPro\Model\Config\Source\Redirect;

/**
 * Class Data
 *
 * @package Mageplaza\SocialLoginPro\Helper
 */
class Data extends StandardHelper
{
    /**
     * @var CurlFactory
     */
    protected $curlFactory;

    /**
     * @var RedirectInterface
     */
    protected $redirect;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param StoreManagerInterface $storeManager
     * @param CurlFactory $curlFactory
     * @param RedirectInterface $redirect
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StoreManagerInterface $storeManager,
        CurlFactory $curlFactory,
        RedirectInterface $redirect
    ) {
        $this->curlFactory = $curlFactory;
        $this->redirect    = $redirect;

        parent::__construct($context, $objectManager, $storeManager);
    }

    /**
     * @param null $storeId
     *
     * @return bool
     */
    public function isGoogleCaptcha($storeId = null)
    {
        $enabled = (int) $this->getGoogleCaptchaEnable($storeId);
        if ($enabled !== Captcha::TYPE_RECAPTCHA) {
            return false;
        }

        if (!$this->getGoogleClientKey($storeId) || !$this->getGoogleClientSecret($storeId)) {
            return false;
        }

        if (empty($this->getRecaptchaForms($storeId))) {
            return false;
        }

        return true;
    }

    /**
     * @param null $storeId
     *
     * @return int
     */
    public function getGoogleCaptchaEnable($storeId = null)
    {
        return $this->getConfigGeneral('captcha/enabled', $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return string
     */
    public function getGoogleClientKey($storeId = null)
    {
        return $this->getConfigGeneral('captcha/recaptcha_client_key', $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return string
     */
    public function getGoogleClientSecret($storeId = null)
    {
        return $this->getConfigGeneral('captcha/recaptcha_client_secret', $storeId);
    }

    /**
     * @param null $storeId
     *
     * @return array
     */
    public function getRecaptchaForms($storeId = null)
    {
        $forms = $this->getConfigGeneral('captcha/recaptcha_forms', $storeId);

        return explode(',', $forms);
    }

    /**
     * @param null $storeId
     *
     * @return string|null
     */
    public function getGoogleReCaptchaType($storeId = null)
    {
        return $this->getConfigGeneral('captcha/recaptcha_type', $storeId);
    }

    /**
     * get reCAPTCHA server response
     *
     * @param null $recaptcha
     *
     * @return array
     */
    public function verifyResponse($recaptcha = null)
    {
        $result = ['success' => false];

        $recaptcha = $recaptcha ?: $this->_request->getParam('g-recaptcha-response');
        if (!$recaptcha) {
            $result['message'] = __('The response parameter is missing.');

            return $result;
        }

        $curl = $this->curlFactory->create();
        $curl->write(
            Request::METHOD_POST,
            $this->getVerifyUrl(),
            '1.1',
            [],
            http_build_query(
                [
                    'secret'   => $this->getGoogleClientSecret(),
                    'remoteip' => $this->_request->getClientIp(),
                    'response' => $recaptcha,
                ]
            )
        );

        try {
            $resultCurl = $curl->read();
            if (!empty($resultCurl)) {
                $responseBody = self::extractBody($resultCurl);
                $responses    = Data::jsonDecode($responseBody);

                if (isset($responses['success']) && $responses['success'] === true) {
                    $result['success'] = true;
                } else {
                    $result['message'] = __('The request is invalid or malformed.');
                }
            } else {
                $result['message'] = __('The request is invalid or malformed.');
            }
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
        }

        $curl->close();

        return $result;
    }

    /**
     * @return string
     */
    protected function getVerifyUrl()
    {
        return 'https://www.google.com/recaptcha/api/siteverify';
    }

    /**
     * @param null $storeId
     *
     * @return mixed|string
     * @throws NoSuchEntityException
     */
    public function getRedirectUrl($storeId = null)
    {
        $url    = '';
        $config = $this->getConfigGeneral('redirect_url', $storeId);

        switch ($config) {
            case Redirect::CUSTOM_URL:
                $url = $this->getUrlConfig();
                break;
            case Redirect::CUSTOMER_DASHBOARD:
                $url = $this->_urlBuilder->getUrl('customer/account/login');
                break;
            case Redirect::HOME_PAGE:
                $url = $this->storeManager->getStore()->getBaseUrl();
                break;
            case Redirect::CMS_PAGE:
                $url = $this->getCmsPage();
                break;
            case Redirect::PREVIOUS_PAGE:
                $url = '';
                break;
        }

        return $url;
    }

    /**
     * @param null $storeId
     *
     * @return string
     */
    public function getCmsPage($storeId = null)
    {
        $cmsURL = $this->getConfigGeneral('cms_page', $storeId);

        return $this->_urlBuilder->getUrl($cmsURL);
    }

    /**
     * @param null $storeId
     *
     * @return mixed
     */
    public function getUrlConfig($storeId = null)
    {
        return $this->getConfigGeneral('custom_url', $storeId);
    }

    /**
     * @param $url
     *
     * @return string
     */
    public function redirectUrl($url)
    {
        return $this->_getUrl($url, ['_secure' => $this->isSecure()]);
    }

    /**
     * @param null $storeId
     *
     * @return string
     */
    public function getPublicKeyOdnoklassniki($storeId = null)
    {
        $publicKey = trim($this->getConfigValue('sociallogin/odnoklassniki/public_key', $storeId) ?: '');

        return $publicKey;
    }

    /**
     * @return int
     * @throws NoSuchEntityException
     */
    public function getStoreId()
    {
        return $this->storeManager->getStore()->getId();
    }

    /**
     * @param null $storeId
     * btnPosition     *
     *
     * @return mixed
     */
    public function getSocialBtnPosition($storeId = null)
    {
        return $this->getConfigGeneral('social_btn_position', $storeId);
    }

    /**
     * Extract the body from a response string
     *
     * @param string $response_str
     *
     * @return string
     */
    public static function extractBody($response_str)
    {
        $parts = preg_split('|(?:\r\n){2}|m', $response_str, 2);
        if (isset($parts[1])) {
            return $parts[1];
        }

        return '';
    }
}
