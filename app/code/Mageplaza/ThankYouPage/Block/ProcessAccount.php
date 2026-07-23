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
 * @package     Mageplaza_ThankYouPage
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ThankYouPage\Block;

use Magento\Checkout\Block\Registration as CheckoutRegistration;
use Magento\Checkout\Model\Session;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Registration;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Address\Validator;
use Mageplaza\ThankYouPage\Helper\Data;

/**
 * Class ProcessAccount
 * @package Mageplaza\ThankYouPage\Block
 */
class ProcessAccount extends CheckoutRegistration
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * ProcessAccount constructor.
     *
     * @param Template\Context $context
     * @param Session $checkoutSession
     * @param CustomerSession $customerSession
     * @param Registration $registration
     * @param AccountManagementInterface $accountManagement
     * @param OrderRepositoryInterface $orderRepository
     * @param Validator $addressValidator
     * @param Data $helperData
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Session $checkoutSession,
        CustomerSession $customerSession,
        Registration $registration,
        AccountManagementInterface $accountManagement,
        OrderRepositoryInterface $orderRepository,
        Validator $addressValidator,
        Data $helperData,
        array $data = []
    ) {
        $this->helperData = $helperData;

        parent::__construct(
            $context,
            $checkoutSession,
            $customerSession,
            $registration,
            $accountManagement,
            $orderRepository,
            $addressValidator,
            $data
        );
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function toHtml()
    {
        if ($this->customerSession->isLoggedIn()
            || !$this->registration->isAllowed()
            || !$this->accountManagement->isEmailAvailable($this->getEmailAddress())
            || !$this->validateAddresses()
            || !$this->checkEnableBlock('account')
        ) {
            return '';
        }

        return parent::toHtml();
    }

    /**
     * @param $name
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function checkEnableBlock($name)
    {
        return $this->helperData->checkEnableBlock($name);
    }

    /**
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function checkCustom()
    {
        return $this->helperData->checkCustom();
    }
}
