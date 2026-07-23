<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Controller\Adminhtml\City;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Api\Data\RegionInterface;
use Secomm\AddressDropdown\Command\City\DeleteByIdCommand;
use Secomm\AddressDropdown\Helper\Data;

/**
 * Delete City controller.
 */
class Delete extends Action implements HttpPostActionInterface, HttpGetActionInterface
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
     * @param Data $data
     */
    public function __construct(
        Context           $context,
        DeleteByIdCommand $deleteByIdCommand,
        Data                   $data
    )
    {
        parent::__construct($context);
        $this->deleteByIdCommand = $deleteByIdCommand;
        $this->data = $data;
    }

    /**
     * Delete City action.
     *
     * @return ResultInterface
     */
    public function execute()
    {
        /** @var ResultInterface $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $entityId = (int)$this->getRequest()->getParam(CityInterface::CITY_ID);
        $regionId = $this->data->getRegionIdByCityId($entityId);
        $resultRedirect->setPath('*/*/', [
            CityInterface::REGION_ID => $regionId,
        ]);

        try {
            $this->deleteByIdCommand->execute($entityId);
            $this->messageManager->addSuccessMessage(__('You have successfully deleted City entity'));
        } catch (CouldNotDeleteException|NoSuchEntityException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $resultRedirect;
    }
}
