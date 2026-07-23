<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Controller\Adminhtml\Region;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel\CollectionFactory;
use Secomm\AddressDropdown\Command\Region\DeleteByIdCommand;

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
     * @var DeleteByIdCommand
     */
    private DeleteByIdCommand $deleteByIdCommand;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param DeleteByIdCommand $deleteByIdCommand
     */
    public function __construct(
        Context           $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        DeleteByIdCommand $deleteByIdCommand
    )
    {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->deleteByIdCommand = $deleteByIdCommand;
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
            if ($selected) {
                $collection = $this->filter->getCollection($this->collectionFactory->create());
                $collectionSize = $collection->getSize();

                foreach ($collection as $item) {
                    try {
                        $this->deleteByIdCommand->execute($item->getId());
                    } catch (\Exception $exception) {
                        $collectionSize -= 1;
                    }
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