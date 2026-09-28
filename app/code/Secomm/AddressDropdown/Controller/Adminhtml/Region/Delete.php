<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Controller\Adminhtml\Region;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use Secomm\AddressDropdown\Api\Data\RegionInterface;
use Secomm\AddressDropdown\Command\Region\DeleteByIdCommand;
use Secomm\AddressDropdown\Helper\Data;

/**
 * Delete Region controller.
 */
class Delete extends Action implements HttpPostActionInterface
{
    /**
     * Authorization level of a basic admin session.
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Secomm_AddressDropdown::management';

    /**
     * @var DeleteByIdCommand
     */
    private DeleteByIdCommand $deleteByIdCommand;

    /**
     * @var Data
     */
    private Data $data;

    /**
     * @param Context $context
     * @param DeleteByIdCommand $deleteByIdCommand
     */
    public function __construct(
        Context           $context,
        DeleteByIdCommand $deleteByIdCommand,
        Data $data
    )
    {
        parent::__construct($context);
        $this->deleteByIdCommand = $deleteByIdCommand;
        $this->data = $data;
    }

    /**
     * Delete Region action.
     *
     * @return ResultInterface
     */
    public function execute()
    {
        /** @var ResultInterface $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $entityId = (int)$this->getRequest()->getParam(RegionInterface::REGION_ID);
        $countryId = $this->data->getCountryIdByRegionId($entityId);
        $resultRedirect->setPath('*/region/', [RegionInterface::COUNTRY_ID => $countryId]);

        // TASK-SEC-A3: destructive actions are POST-only (form-key validated) — a GET
        // navigation to this URL must never mutate data, even where the dispatcher allows it.
        if (!$this->getRequest()->isPost()) {
            $this->messageManager->addErrorMessage(__('Invalid request method. Delete requires POST.'));
            return $resultRedirect;
        }

        try {
            $this->deleteByIdCommand->execute($entityId);
            $this->messageManager->addSuccessMessage(__('You have successfully deleted Region entity'));
        } catch (CouldNotDeleteException|NoSuchEntityException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $resultRedirect;
    }
}
