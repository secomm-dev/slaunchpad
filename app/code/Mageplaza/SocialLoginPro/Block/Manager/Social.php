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

namespace Mageplaza\SocialLoginPro\Block\Manager;

use Magento\Customer\Model\Session;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Element\Template\Context;
use Mageplaza\SocialLogin\Block\Popup\Social as SocialLogin;
use Mageplaza\SocialLogin\Helper\Social as SocialHelper;
use Mageplaza\SocialLogin\Model\SocialFactory;

/**
 * Class Social
 *
 * @package Mageplaza\SocialLoginPro\Block\Manager
 */
class Social extends SocialLogin
{
    /**
     * @type SocialFactory
     */
    protected $socialFactory;

    /**
     * @type Session
     */
    protected $customerSession;

    /**
     * @var ThemeInterface
     */
    protected $_themeProvider;

    /**
     * Social constructor.
     *
     * @param Context $context
     * @param SocialHelper $socialHelper
     * @param SocialFactory $socialFactory
     * @param Session $customerSession
     * @param ThemeProviderInterface $themeInterface
     * @param array $data
     */
    public function __construct(
        Context $context,
        SocialHelper $socialHelper,
        SocialFactory $socialFactory,
        Session $customerSession,
        ThemeProviderInterface $themeInterface,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->socialFactory   = $socialFactory;
        $this->_themeProvider  = $themeInterface;

        parent::__construct($context, $socialHelper, $data);
    }

    /**
     * @return string
     */
    public function getAvailableSocialsPro()
    {
        $availableSocials = [];
        $socialTypes      = $this->socialHelper->getSocialTypes();

        foreach ($socialTypes as $socialKey => $socialLabel) {
            $this->socialHelper->setType($socialKey);
            if ($this->socialHelper->isEnabled()) {
                $availableSocials[$socialKey] = [
                    'label'     => $socialTypes[$socialKey],
                    'btnKey'    => $this->getBtnKey($socialKey),
                    'login_url' => $this->getLoginUrl($socialKey, ['manager' => true]),
                    'ajaxUrl'   => $this->getAjaxUrl(),
                    'connected' => $this->isConnected($socialKey)
                ];
            }
        }

        return SocialHelper::jsonEncode($availableSocials);
    }

    /**
     * @param $type
     *
     * @return bool
     */
    public function isConnected($type)
    {
        $socialCollection = $this->socialFactory->create()->getCollection()
            ->addFieldToFilter('customer_id', $this->customerSession->getCustomer()->getId())
            ->addFieldToFilter('type', $type);

        return $socialCollection->getSize() > 0;
    }

    /**
     * @return string
     */
    public function getAjaxUrl()
    {
        if ($this->customerSession->isLoggedIn()) {
            return $this->getUrl('sociallogin/manager/button');
        }

        return '';
    }

    /**
     * @param $key
     *
     * @return string
     */
    public function getBtnKey($key)
    {
        return $key === 'vkontakte' ? 'vk' : $key;
    }

    /**
     * Get Current Theme Name Function
     *
     * @return string
     */
    public function getCurrentTheme()
    {
        $themeId = $this->socialHelper->getConfigValue(DesignInterface::XML_PATH_THEME_ID);

        /**
         * @var ThemeInterface $theme
         */
        $theme = $this->_themeProvider->getThemeById($themeId);

        return $theme->getCode();
    }
}
