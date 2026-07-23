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

namespace Mageplaza\AbandonedCart\Observer;

use Exception;
use Magento\Customer\Model\Data\Customer;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Mageplaza\AbandonedCart\Helper\Data as HelperData;
use Psr\Log\LoggerInterface;

/**
 * Class AceCustomerSaveAfter
 * @package Mageplaza\AbandonedCart\Observer
 */
class AceCustomerSaveAfter implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var HelperData
     */
    protected $helperData;

    /**
     * @var CustomerCollectionFactory
     */
    protected $customerCollectionFactory;

    /**
     * AceCustomerSaveAfter constructor.
     *
     * @param LoggerInterface $logger
     * @param HelperData $helperData
     * @param CustomerCollectionFactory $customerCollectionFactory
     */
    public function __construct(
        LoggerInterface $logger,
        HelperData $helperData,
        CustomerCollectionFactory $customerCollectionFactory
    ) {
        $this->logger                    = $logger;
        $this->helperData                = $helperData;
        $this->customerCollectionFactory = $customerCollectionFactory;
    }

    /**
     * @param Observer $observer
     *
     * @return $this|void
     * @throws Exception
     */
    public function execute(Observer $observer)
    {
        if (!$this->helperData->isEnabled()) {
            return $this;
        }

        /** @var Customer $customer */
        $customer       = $observer->getEvent()->getCustomer();
        $request        = $observer->getEvent()->getRequest();
        $customerData   = $request->getParam('customer');
        $mpAceBlacklist = isset($customerData['mp_ace_blacklist']) ? $customerData['mp_ace_blacklist'] : 0;
        if ($customer->getId()) {
            $mpCustomer = $this->customerCollectionFactory->create()
                ->addFieldToFilter('entity_id', ['eq' => $customer->getId()]);

            $this->helperData->updateData(
                $mpCustomer->getConnection(),
                [$customer->getId()],
                $mpCustomer->getMainTable(),
                $mpAceBlacklist
            );
        }

        return $this;
    }
}
