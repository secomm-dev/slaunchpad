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

namespace Mageplaza\SocialLoginPro\Plugin\Controller;

use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Mageplaza\SocialLogin\Controller\Popup\Create as PopupCreate;
use Mageplaza\SocialLoginPro\Helper\Data;
use Mageplaza\SocialLoginPro\Model\Config\Source\Captcha;

/**
 * Class Popup
 *
 * @package Mageplaza\SocialLogin\Block
 */
class Create
{
    /**
     * @type JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * Create constructor.
     *
     * @param JsonFactory $resultJsonFactory
     * @param Data $helper
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        Data $helper
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->helper            = $helper;
    }

    /**
     * @param PopupCreate $subject
     * @param $proceed
     *
     * @return bool
     */
    public function aroundCheckCaptcha(PopupCreate $subject, $proceed)
    {
        if ($this->helper->isGoogleCaptcha() || ($this->helper->getGoogleCaptchaEnable() === Captcha::TYPE_NO)) {
            return true;
        }

        return $proceed();
    }

    /**
     * @param PopupCreate $subject
     * @param $proceed
     *
     * @return Json
     */
    public function aroundExecute(PopupCreate $subject, $proceed)
    {
        /**
         * @var Json $resultJson
         */
        $resultJson = $this->resultJsonFactory->create();

        if ($this->helper->isGoogleCaptcha()) {
            if (in_array('user_create', (array) $this->helper->getRecaptchaForms(), true)) {
                $response = $this->helper->verifyResponse();
                if (isset($response['success']) && !$response['success']) {
                    $result = [
                        'success' => false,
                        'message' => $response['message']
                    ];

                    return $resultJson->setData($result);
                }
            }
        }

        return $proceed();
    }
}
