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

namespace Mageplaza\RMA\Plugin\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Mageplaza\GoogleRecaptcha\Observer\Captcha as CaptchaObserver;
use Mageplaza\RMA\Helper\Data as HelperData;

/**
 * Class Captcha
 * @package Mageplaza\RMA\Plugin\Observer
 */
class Captcha
{
    /**
     * @var RequestInterface
     */
    protected $_request;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * MassAction constructor.
     *
     * @param RequestInterface $request
     * @param HelperData $helperData
     */
    public function __construct(
        RequestInterface $request,
        HelperData $helperData
    ) {
        $this->_request = $request;
        $this->_helperData = $helperData;
    }

    /**
     * @param CaptchaObserver $captcha
     * @param callable $proceed
     * @param Observer $observer
     * @SuppressWarnings(Unused)
     */
    public function aroundExecute(
        CaptchaObserver $captcha,
        callable $proceed,
        Observer $observer
    ) {
        if ($this->_helperData->getRequestConfig('google_recaptcha')
            || $this->_request->getFullActionName() !== 'mprma_request_save'
        ) {
            $proceed($observer);
        }
    }
}
