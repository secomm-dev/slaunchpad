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
 * @package     Mageplaza_SocialLoginPro
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\SocialLoginPro\Controller\Manager;

use Exception;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Mageplaza\SocialLogin\Model\SocialFactory;

/**
 * Class Button
 *
 * @package Mageplaza\SocialLoginPro\Controller\Manager
 */
class Button extends Action
{
    /**
     * @type JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @type SocialFactory
     */
    protected $socialFactory;

    /**
     * @type Session
     */
    protected $customerSession;

    /**
     * Button constructor.
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param SocialFactory $socialFactory
     * @param Session $customerSession
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        SocialFactory $socialFactory,
        Session $customerSession
    ) {
        $this->socialFactory     = $socialFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->customerSession   = $customerSession;

        parent::__construct($context);
    }

    /**
     * @return $this|ResponseInterface|ResultInterface
     * @throws Exception
     */
    public function execute()
    {
        /** @var Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();

        $result  = [
            'success' => false,
            'message' => []
        ];
        $request = $this->getRequest()->getParams();

        if (!isset($request['type'])) {
            $result['message'] = __('Can\'t change status. Please try again!');

            return $resultJson->setData($result);
        }
        try {
            $social           = $this->socialFactory->create();
            $socialCollection = $social->getCollection()->addFieldToFilter(
                'customer_id',
                $this->customerSession->getCustomer()->getId()
            )
                ->addFieldToFilter('type', $request['type'])->getAllIds();

            foreach ($socialCollection as $item) {
                $social->load($item)->delete();
            }
            $result['success'] = true;
        } catch (Exception $e) {
            $result['message'] = __('Can\'t change status. Please try again!');
        }

        return $resultJson->setData($result);
    }
}
