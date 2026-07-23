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
 * @package     Mageplaza_AbandonedCart
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\AbandonedCart\Block\Customer;

use Magento\Customer\Model\SessionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mageplaza\AbandonedCart\Helper\Sms;

/**
 * Class AbandonedCartPhone
 * @package Mageplaza\AbandonedCart\Block\Customer
 */
class AbandonedCartPhone extends Template
{
    /**
     * @var Sms
     */
    protected $smsHelper;

    /**
     * @var SessionFactory
     */
    protected $customerSession;

    /**
     * AbandonedCartPhone constructor.
     *
     * @param Context $context
     * @param Sms $smsHelper
     * @param SessionFactory $customerSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        Sms $smsHelper,
        SessionFactory $customerSession,
        array $data = []
    ) {
        $this->smsHelper       = $smsHelper;
        $this->customerSession = $customerSession;

        parent::__construct($context, $data);
    }

    /**
     * @return mixed|string
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    function getCustomerAbandonedCartPhone()
    {
        $customerSession = $this->customerSession->create();
        if ($customerSession->isLoggedIn()) {
            $customerId = $customerSession->getCustomerId();

            return $this->smsHelper->getCustomerAbandonedCartPhone($customerId);
        }

        return '';
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isEnableAbandonedCartNumber()
    {
        $storeId = $this->_storeManager->getStore()->getId();

        return ($this->smsHelper->getSmsConfig('enable_abandon_cart_number', $storeId)
            && $this->smsHelper->getSmsConfig('enabled', $storeId)
            && $this->smsHelper->getConfigGeneral('enabled', $storeId)
        );
    }
}
