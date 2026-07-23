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

namespace Mageplaza\AbandonedCart\Controller\Subscriber;

use Exception;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Newsletter\Model\Subscriber;
use Mageplaza\AbandonedCart\Helper\Data;

/**
 * Class Unsubscribe
 * @package Mageplaza\AbandonedCart\Controller\Subscriber
 */
class Unsubscribe extends Action
{

    /**
     * @var Subscriber
     */
    protected $subscriber;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * Unsubscribe constructor.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param Data $helperData
     * @param Subscriber $subscriber
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Data $helperData,
        Subscriber $subscriber,
        EncryptorInterface $encryptor
    ) {
        $this->helperData        = $helperData;
        $this->subscriber        = $subscriber;
        $this->resultPageFactory = $resultPageFactory;
        $this->encryptor         = $encryptor;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     */
    public function execute()
    {
        if ($this->helperData->isEnabled()) {
            $resultPage = $this->resultPageFactory->create();
            try {
                $resultPage->getConfig()->getTitle()->set(__('You are unsubscribed'));
                $email = $this->encryptor->decrypt($this->_request->getParam('email'));
                if ($email) {
                    $websiteId  = $this->_request->getParam('website_id');
                    $subscriber = $this->subscriber->loadBySubscriberEmail($email, $websiteId);

                    if (!$subscriber->isSubscribed()) {
                        $this->messageManager->addErrorMessage(__('The subscriber has been unsubscribed before.'));
                        $this->getResponse()->sendResponse();
                        $this->_redirect('');
                    }
                    if ($subscriber->isSubscribed()) {
                        $subscriber->unsubscribe();
                        $this->messageManager->addSuccessMessage(__('Unsubscribed succeeded.'));
                    }
                }
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }

            return $resultPage;
        }

        return $this->_redirect('cms');
    }
}
