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

namespace Mageplaza\SocialLoginPro\Block;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template\Context;
use Mageplaza\SocialLogin\Helper\Data;

/**
 * Class Captcha
 *
 * @package Mageplaza\SocialLoginPro\Block
 */
class Captcha extends \Magento\Captcha\Block\Captcha
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * Captcha constructor.
     *
     * @param Context $context
     * @param \Magento\Captcha\Helper\Data $captchaData
     * @param Data $helperData
     * @param array $data
     */
    public function __construct(
        Context $context,
        \Magento\Captcha\Helper\Data $captchaData,
        Data $helperData,
        array $data = []
    ) {
        $this->helper = $helperData;

        parent::__construct($context, $captchaData, $data);
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    protected function _toHtml()
    {
        $enableCaptchaMp = $this->helper->isEnabledGGRecaptcha();
        if (!$enableCaptchaMp) {
            $blockPath = $this->_captchaData->getCaptcha($this->getFormId())->getBlockName();
            $block     = $this->getLayout()->createBlock($blockPath);
            $block->setData($this->getData());
            $block->setTemplate('Magento_Captcha::default.phtml');

            return $block->toHtml();
        }

        return '';
    }
}
