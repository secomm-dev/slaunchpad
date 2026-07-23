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

namespace Mageplaza\AbandonedCart\Controller\Adminhtml\Customer;

use Exception;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Customer\Controller\Adminhtml\Index\AbstractMassAction;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Eav\Model\Entity\Collection\AbstractCollection;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Controller\ResultFactory;
use Mageplaza\AbandonedCart\Helper\Data;

/**
 * Class MassUpdateBlacklistEmail
 * @package Mageplaza\AbandonedCart\Controller\Adminhtml\Customer
 */
class MassUpdateBlacklistEmail extends AbstractMassAction implements HttpPostActionInterface
{
    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * MassUpdateBlacklistEmail constructor.
     *
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param Data $helperData
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        CustomerRepositoryInterface $customerRepository,
        Data $helperData
    ) {
        $this->customerRepository = $customerRepository;
        $this->helperData         = $helperData;

        parent::__construct($context, $filter, $collectionFactory);
    }

    /**
     * @param AbstractCollection $collection
     *
     * @return Redirect|ResponseInterface|ResultInterface
     * @throws Exception
     */
    protected function massAction(AbstractCollection $collection)
    {
        $customersUpdated = 0;
        $request          = $this->getRequest()->getParams();
        $mpAceBlacklist   = isset($request['mp_ace_blacklist']) ? $request['mp_ace_blacklist'] : 0;
        foreach ($collection->getAllIds() as $customerId) {
            $mpCustomer = $this->collectionFactory->create()
                ->addFieldToFilter('entity_id', ['eq' => $customerId]);

            $this->helperData->updateData(
                $mpCustomer->getConnection(),
                [$customerId],
                $mpCustomer->getMainTable(),
                $mpAceBlacklist
            );
            $customersUpdated++;
        }

        if ($customersUpdated) {
            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) were updated.', $customersUpdated));
        }
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('customer/index/index');

        return $resultRedirect;
    }
}
