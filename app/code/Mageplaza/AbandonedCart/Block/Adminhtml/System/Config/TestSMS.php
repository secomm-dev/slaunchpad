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
 * @package     Mageplaza_SmsNotification
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\AbandonedCart\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Mageplaza\AbandonedCart\Helper\Sms;

/**
 * Class TestSMS
 * @package Mageplaza\AbandonedCart\Block\Adminhtml\System\Config
 */
class TestSMS extends Field
{
    /**
     * @var Sms
     */
    private $smsHelper;

    /**
     * TestSMS constructor.
     *
     * @param Context $context
     * @param Sms $smsHelper
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        Context $context,
        Sms $smsHelper,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        $this->smsHelper = $smsHelper;

        parent::__construct($context, $data, $secureRenderer);
    }

    /**
     * @var string
     */
    protected $_template = 'Mageplaza_AbandonedCart::system/config/test.phtml';

    /**
     * Remove scope label
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    /**
     * @return array|mixed|string
     */
    public function getAdminPhone()
    {
        return $this->smsHelper->getSenderPhoneNumber() ?: '';
    }

    /**
     * @return array|mixed|string
     */
    public function getTwilioSID()
    {
        return $this->smsHelper->getTwilioSID() ?: '';
    }

    /**
     * @return array|mixed|string
     */
    public function getTwilioToken()
    {
        return $this->smsHelper->getTwilioToken() ?: '';
    }

    /**
     * Return element html
     *
     * @param AbstractElement $element
     *
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * @return string
     */
    public function getButtonHtml()
    {
        /**
         * @var Button $button
         */
        try {
            $button = $this->getLayout()->createBlock(
                Button::class
            )->setData(
                [
                    'id'    => 'credential_button',
                    'label' => __('Send Test SMS'),
                    'class' => 'primary',
                ]
            );

            return $button->toHtml();
        } catch (LocalizedException $e) {
            $this->_logger->critical($e->getMessage());
        }

        return '';
    }

    /**
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getUrl('abandonedcart/sms/test');
    }
}
