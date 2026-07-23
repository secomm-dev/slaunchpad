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

namespace Mageplaza\ThankYouPage\Controller\Subscribe;

use Exception;
use Magento\Customer\Api\AccountManagementInterface as CustomerAccountManagement;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\ThankYouPage\Helper\Data;
use Mageplaza\ThankYouPage\Model\Config\Source\Pagetype;
use Mageplaza\ThankYouPage\Model\Generate;
use Magento\Newsletter\Model\SubscriptionManagerInterface;

/**
 * Class NewAction
 * @package Mageplaza\ThankYouPage\Controller\Subscribe
 */
class NewAction extends \Magento\Newsletter\Controller\Subscriber\NewAction
{
    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var Generate
     */
    protected $generateCoupon;

    /**
     * @var SessionManagerInterface
     */
    protected $session;

    /**
     * NewAction constructor.
     *
     * @param Context $context
     * @param Data $helperData
     * @param Generate $generate
     * @param SubscriberFactory $subscriberFactory
     * @param Session $customerSession
     * @param StoreManagerInterface $storeManager
     * @param CustomerUrl $customerUrl
     * @param SessionManagerInterface $session
     * @param CustomerAccountManagement $customerAccountManagement
     * @param SubscriptionManagerInterface $subscriptionManager
     */
    public function __construct(
        Context $context,
        Data $helperData,
        Generate $generate,
        SubscriberFactory $subscriberFactory,
        Session $customerSession,
        StoreManagerInterface $storeManager,
        CustomerUrl $customerUrl,
        SessionManagerInterface $session,
        CustomerAccountManagement $customerAccountManagement,
        SubscriptionManagerInterface $subscriptionManager
    ) {
        $this->helperData     = $helperData;
        $this->generateCoupon = $generate;
        $this->session        = $session;

        parent::__construct(
            $context,
            $subscriberFactory,
            $customerSession,
            $storeManager,
            $customerUrl,
            $customerAccountManagement,
            $subscriptionManager
        );
    }

    /**
     * New subscription action
     *
     * @return Redirect|void
     * @throws LocalizedException
     */
    public function execute()
    {
        $success = false;
        if ($this->getRequest()->isPost() && $this->getRequest()->getPost('email')) {
            $email = (string) $this->getRequest()->getPost('email');

            try {
                $this->validateEmail($email);

                $subscriber = $this->_subscriberFactory->create()->loadByEmail($email);
                if ($subscriber->getId()
                    && $subscriber->getSubscriberStatus() == Subscriber::STATUS_SUBSCRIBED
                ) {
                    throw new LocalizedException(
                        __('This email address is already subscribed.')
                    );
                }

                $status = $this->_subscriberFactory->create()->subscribe($email);
                if ($status == Subscriber::STATUS_NOT_ACTIVE) {
                    $success = true;
                    $this->messageManager->addSuccess(__('The confirmation request has been sent.'));
                } else {
                    $success = true;
                    $this->messageManager->addSuccess(__('Thank you for your subscription.'));
                }
            } catch (LocalizedException $e) {
                $this->messageManager->addException(
                    $e,
                    __('There was a problem with the subscription: %1', $e->getMessage())
                );
            } catch (Exception $e) {
                $this->messageManager->addException($e, __('Something went wrong with the subscription.'));
            }
        }
        if ($this->helperData->isEnabled() &&
            $this->helperData->isNewsletterSuccessPageEnable() &&
            $this->helperData->getTemplateData(Pagetype::NEWSLETTER) != null &&
            $success === true
        ) {
            $this->session->start();
            $this->session->setData('coupon_code', $this->helperData->getCouponCode(Pagetype::NEWSLETTER));

            return $this->resultRedirectFactory->create()->setPath($this->helperData->getNewsletterSuccessPageRoute());
        }
        $this->getResponse()->setRedirect($this->_redirect->getRedirectUrl());
    }

    /**
     * @param string $email
     *
     * @throws LocalizedException
     */
    public function validateEmail($email)
    {
        $this->validateEmailFormat($email);
        $this->validateGuestSubscription();
        $this->validateEmailAvailable($email);
    }
}
