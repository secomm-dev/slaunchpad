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

use Magento\Customer\Model\Context as ModelContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Mageplaza\SocialLoginPro\Helper\Data as HelperData;

/**
 * Class OneTap
 * @package Mageplaza\SocialLoginPro\Block
 */
class OneTap extends Template
{
    /**
     * @var HelperData
     */
    protected $helperData;

    /**
     * @var HttpContext
     */
    protected $httpContext;

    /**
     * @var FormKey
     */
    protected $formKey;

    /**
     * @var SecureHtmlRenderer
     */
    protected $renderTag;

    /**
     * OneTap constructor.
     *
     * @param HelperData $helperData
     * @param HttpContext $httpContext
     * @param FormKey $formKey
     * @param Context $context
     * @param SecureHtmlRenderer $renderTag
     * @param array $data
     */
    public function __construct(
        HelperData $helperData,
        HttpContext $httpContext,
        FormKey $formKey,
        Context $context,
        SecureHtmlRenderer $renderTag,
        array $data = []
    ) {
        $this->helperData  = $helperData;
        $this->httpContext = $httpContext;
        $this->formKey     = $formKey;
        $this->renderTag   = $renderTag;
        parent::__construct($context, $data);
    }

    /**
     * @return array|mixed
     */
    public function getClientId()
    {
        return $this->helperData->getConfigValue('sociallogin/google/app_id');
    }

    /**
     * @return mixed|null
     */
    public function isLoggedIn()
    {
        return $this->httpContext->getValue(ModelContext::CONTEXT_AUTH);
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function getLoginUrl()
    {
        return $this->_urlBuilder->getUrl('sociallogin/onetap/login', ['form_key' => $this->formKey->getFormKey()]);
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isOneTapEnabled()
    {
        $storeId = $this->helperData->getStoreId();

        return $this->helperData->getConfigValue('sociallogin/google/is_enabled', $storeId)
            && $this->helperData->getConfigValue('sociallogin/google/one_tap', $storeId);
    }

    /**
     * @return string
     */
    public function addLibrary()
    {
        return $this->renderTag->renderTag(
            'script',
            ['src' => 'https://accounts.google.com/gsi/client', 'async' => 'async', 'defer' => 'defer'],
            false
        );
    }
}
