<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Controller\Adminhtml\City;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityCollectionFactory as CollectionFactory;

class MassDelete extends \Magento\Backend\App\Action
{
    /**
     * Authorization level of a basic admin session.
     */
    public const ADMIN_RESOURCE = 'Secomm_AddressDropdown::management';

    /**
     * @var Filter
     */
    protected Filter $filter;

    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $collectionFactory;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        Context           $context,
        Filter $filter,
        CollectionFactory $collectionFactory
    )
    {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context);
    }

    /**
     * @throws LocalizedException
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());

        try {
            $selected = $this->getRequest()->getParam(Filter::SELECTED_PARAM);
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            if ($selected) {
                $collectionSize = $collection->getSize();

                foreach ($collection as $item) {
                    $item->delete();
                }

                $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $collectionSize));
            } else {
                $this->messageManager->addErrorMessage(__('Something went wrong. Please try again.'));
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Something went wrong. Please try again.'));
        }

        return $resultRedirect;
    }
}