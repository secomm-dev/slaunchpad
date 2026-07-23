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

namespace Mageplaza\SocialLoginPro\Plugin\Block\Adminhtml\System\Account\Edit;

use Closure;
use Magento\Backend\Model\Auth\Session;
use Magento\Config\Model\Config\Source\Enabledisable;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutInterface;
use Magento\User\Model\UserFactory;
use Mageplaza\SocialLogin\Helper\Data as HelperData;
use Mageplaza\SocialLoginPro\Block\Adminhtml\Social;

/**
 * Class Form
 *
 * @package Mageplaza\SocialLoginPro\Plugin\Block\Adminhtml\System\Account\Edit
 */
class Form
{
    /**
     * @var Enabledisable
     */
    protected $_enableDisable;

    /**
     * @var Registry
     */
    protected $_coreRegistry;

    /**
     * @var LayoutInterface
     */
    protected $_layout;

    /**
     * @var Session
     */
    protected $_authSession;

    /**
     * @var UserFactory
     */
    protected $_userFactory;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * Form constructor.
     *
     * @param Enabledisable $enableDisable
     * @param Registry $coreRegistry
     * @param LayoutInterface $layout
     * @param Session $authSession
     * @param UserFactory $userFactory
     * @param HelperData $helperData
     */
    public function __construct(
        Enabledisable $enableDisable,
        Registry $coreRegistry,
        LayoutInterface $layout,
        Session $authSession,
        UserFactory $userFactory,
        HelperData $helperData
    ) {
        $this->_enableDisable = $enableDisable;
        $this->_coreRegistry  = $coreRegistry;
        $this->_layout        = $layout;
        $this->_authSession   = $authSession;
        $this->_userFactory   = $userFactory;
        $this->_helperData    = $helperData;
    }

    /**
     * @param \Magento\Backend\Block\System\Account\Edit\Form $subject
     * @param Closure $proceed
     *
     * @return mixed
     */
    public function aroundGetFormHtml(
        \Magento\Backend\Block\System\Account\Edit\Form $subject,
        Closure $proceed
    ) {
        $form = $subject->getForm();

        $userId = $this->_authSession->getUser()->getId();
        $user   = $this->_userFactory->create()->load($userId);
        $user->unsetData('password');

        if (is_object($form) && $this->_helperData->isEnabled()) {
            $fieldset = $form->addFieldset(
                'mp_connect_sl',
                ['legend' => __('Connect Social Channel (for admin login)')]
            );
            $fieldset->addField(
                'mp_connect_admin_account',
                'label',
                [
                    'name'  => 'mp_connect_admin_account',
                    'label' => ''
                ]
            )->setAfterElementHtml($this->getButtonsHtml());
        }

        return $proceed();
    }

    /**
     * @return string
     */
    public function getButtonsHtml()
    {
        return $this->_layout
            ->createBlock(Social::class)
            ->setTemplate('Mageplaza_SocialLoginPro::system/account/buttons.phtml')
            ->toHtml();
    }
}
