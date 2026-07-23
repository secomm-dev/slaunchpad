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

namespace Mageplaza\SocialLoginPro\Plugin\Model;

use Closure;
use Exception;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Mageplaza\SocialLogin\Model\SocialFactory;

/**
 * Class Social
 *
 * @package Mageplaza\SocialLo  gin\Model
 */
class Social
{
    /**
     * @type Session
     */
    protected $customerSession;

    /**
     * @type
     */
    protected $socialFactory;

    /**
     * @type CustomerFactory
     */
    protected $customerFactory;

    /**
     * @type \Mageplaza\SocialLogin\Helper\Social
     */
    protected $apiHelper;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var DateTime
     */
    protected $_dateTime;

    /**
     * Social constructor.
     *
     * @param Session $customerSession
     * @param CustomerFactory $customerFactory
     * @param SocialFactory $socialFactory
     * @param \Mageplaza\SocialLogin\Helper\Social $apiHelper
     * @param ManagerInterface $messageManager
     * @param DateTime $dateTime
     */
    public function __construct(
        Session $customerSession,
        CustomerFactory $customerFactory,
        SocialFactory $socialFactory,
        \Mageplaza\SocialLogin\Helper\Social $apiHelper,
        ManagerInterface $messageManager,
        DateTime $dateTime
    ) {
        $this->customerFactory = $customerFactory;
        $this->customerSession = $customerSession;
        $this->socialFactory   = $socialFactory;
        $this->apiHelper       = $apiHelper;
        $this->messageManager  = $messageManager;
        $this->_dateTime       = $dateTime;
    }

    /**
     * @param \Mageplaza\SocialLogin\Model\Social $subject
     * @param Closure $proceed
     * @param $identify
     * @param $type
     *
     * @return $this|mixed
     * @throws Exception
     */
    public function aroundGetCustomerBySocial(
        \Mageplaza\SocialLogin\Model\Social $subject,
        Closure $proceed,
        $identify,
        $type
    ) {
        $customerSocial = $proceed($identify, $type);
        $customerId     = $this->customerSession->getId();

        if ($customerId) {
            if (!$customerSocial->getId()) {
                $social = $this->socialFactory->create()
                    ->getCollection()->addFieldToFilter('customer_id', $customerId)
                    ->addFieldToFilter('social_id', $identify);
                if (empty($social->getData())) {
                    try {
                        $subject->setData(
                            [
                                'social_id'              => $identify,
                                'customer_id'            => $customerId,
                                'type'                   => $type,
                                'is_send_password_email' => $this->apiHelper->canSendPassword(),
                                'social_created_at'      => $this->_dateTime->date()
                            ]
                        )
                            ->setId(null)
                            ->save();
                    } catch (Exception $e) {
                        throw $e;
                    }
                }
            } else {
                if ((int)$customerSocial->getCustomerId() !== (int)$customerId) {
                    // 1 social account only reference to 1 store account, 1 store account can reference to many social account.
                    $this->messageManager->addError(__('This social channel has been already used by another store account.'));
                }
            }
            // If customer have multiple accounts common use a social account
            $customerSocial = $this->customerFactory->create()->load($customerId);
        }

        return $customerSocial;
    }
}
