<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Controller\Adminhtml\SubCity;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InvalidArgumentException;
use Secomm\AddressDropdown\Api\Data\SubCityInterface;
use Secomm\AddressDropdown\Api\Data\SubCityInterfaceFactory;
use Secomm\AddressDropdown\Command\SubCity\SaveCommand;

/**
 * Save SubCity controller action.
 */
class Save extends Action implements HttpPostActionInterface
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Secomm_AddressDropdown::management';

    /**
     * @var DataPersistorInterface
     */
    private DataPersistorInterface $dataPersistor;

    /**
     * @var SaveCommand
     */
    private SaveCommand $saveCommand;

    /**
     * @var SubCityInterfaceFactory
     */
    private SubCityInterfaceFactory $entitySubCityDataFactory;


    /**
     * @param Context $context
     * @param DataPersistorInterface $dataPersistor
     * @param SaveCommand $saveCommand
     * @param SubCityInterfaceFactory $entitySubCityDataFactory
     */
    public function __construct(
        Context                 $context,
        DataPersistorInterface  $dataPersistor,
        SaveCommand             $saveCommand,
        SubCityInterfaceFactory $entitySubCityDataFactory,
    )
    {
        parent::__construct($context);
        $this->dataPersistor = $dataPersistor;
        $this->saveCommand = $saveCommand;
        $this->entitySubCityDataFactory = $entitySubCityDataFactory;
    }

    /**
     * Save SubCity Action.
     *
     * @return ResultInterface
     * @throws InvalidArgumentException
     */
    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $params = $this->getRequest()->getParam('general');

        try {
            /** @var SubCityInterface|DataObject $entityModel */
            $entityModel = $this->entitySubCityDataFactory->create();

            $entityModel->addData($params);
            $subCityId = $this->saveCommand->execute($entityModel);

            $this->messageManager->addSuccessMessage(
                __('SubCity data was saved successfully')
            );
            $this->dataPersistor->clear('entity');
        } catch (CouldNotSaveException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            $this->dataPersistor->set('entity', $params);

            if (empty($params[SubCityInterface::SUB_CITY_ID])) {
                return $resultRedirect->setPath('*/subcity/new',
                    [
                        SubCityInterface::CITY_ID => $params[SubCityInterface::CITY_ID]
                    ]);
            }
        }

        return $resultRedirect->setPath('*/subcity/edit', [
            SubCityInterface::SUB_CITY_ID => $subCityId ?? $params[SubCityInterface::SUB_CITY_ID]
        ]);
    }
}
