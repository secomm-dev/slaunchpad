<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Controller\Adminhtml\City;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InvalidArgumentException;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Api\Data\CityInterfaceFactory;
use Secomm\AddressDropdown\Command\City\SaveCommand;
use Secomm\AddressDropdown\Helper\Data;

/**
 * Save City controller action.
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
     * @var CityInterfaceFactory
     */
    private CityInterfaceFactory $entityDataFactory;

    /**
     * @var Data
     */
    private Data $data;

    /**
     * @param Context $context
     * @param DataPersistorInterface $dataPersistor
     * @param SaveCommand $saveCommand
     * @param CityInterfaceFactory $entityDataFactory
     * @param Data $data
     */
    public function __construct(
        Context                $context,
        DataPersistorInterface $dataPersistor,
        SaveCommand            $saveCommand,
        CityInterfaceFactory   $entityDataFactory,
        Data                   $data
    )
    {
        parent::__construct($context);
        $this->dataPersistor = $dataPersistor;
        $this->saveCommand = $saveCommand;
        $this->entityDataFactory = $entityDataFactory;
        $this->data = $data;
    }

    /**
     * Save City Action.
     *
     * @return ResultInterface
     * @throws InvalidArgumentException
     */
    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $params = $this->getRequest()->getParam('general');

        try {
            /** @var CityInterface|DataObject $entityModel */
            $entityModel = $this->entityDataFactory->create();
            $entityModel->addData($params);
            $cityId = $this->saveCommand->execute($entityModel);
            $this->messageManager->addSuccessMessage(
                __('The City data was saved successfully')
            );
            $this->dataPersistor->clear('entity');
        } catch (CouldNotSaveException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            $this->dataPersistor->set('entity', $params);

            if (empty($params[CityInterface::CITY_ID])) {
                return $resultRedirect->setPath(
                    '*/*/new',[
                        CityInterface::REGION_ID => $params[CityInterface::REGION_ID]
                    ]);
            }
        }

        return $resultRedirect->setPath('*/*/edit', [
            CityInterface::CITY_ID => $cityId ?? $params[CityInterface::REGION_ID]
        ]);
    }
}
