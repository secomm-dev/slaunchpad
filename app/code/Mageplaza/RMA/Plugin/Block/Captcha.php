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

namespace Mageplaza\RMA\Plugin\Block;

use Magento\Framework\App\RequestInterface;
use Mageplaza\RMA\Helper\Data as HelperData;
use Mageplaza\GoogleRecaptcha\Block\Captcha as CaptchaBlock;

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
     * Captcha constructor.
     *
     * @param RequestInterface $request
     * @param HelperData $helperData
     */
    public function __construct(
        RequestInterface $request,
        HelperData $helperData,
    ) {
        $this->_request         = $request;
        $this->_helperData      = $helperData;
    }


    /**
     * @param CaptchaBlock $captcha
     * @param $result
     * @return false|mixed|string
     */
    public function afterGetForms(CaptchaBlock $captcha, $result)
    {
        $_dataFormId = [];

        if ($this->_helperData->isEnabled() && $this->_request->getFullActionName() == 'mprma_request_index'
            && $this->_helperData->getRequestConfig('google_recaptcha') && class_exists('Mageplaza\GoogleRecaptcha\Helper\Data')) {
            $CssSelectors = $this->_helperData->getObject('Mageplaza\GoogleRecaptcha\Helper\Data')->getCssSelectors();
            $_dataFormId[]  = '.form.mp-edit-rma-form';
            $data = array_merge($CssSelectors, $_dataFormId);
            return json_encode($data);
        }

        return $result;
    }
}
