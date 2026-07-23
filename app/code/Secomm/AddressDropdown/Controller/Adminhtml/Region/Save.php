<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Controller\Adminhtml\Region;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Secomm\AddressDropdown\Api\Data\RegionInterface;
use Secomm\AddressDropdown\Api\Data\RegionInterfaceFactory;
use Secomm\AddressDropdown\Command\Region\SaveCommand;
use Secomm\AddressDropdown\Helper\Data;


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
     * @var RegionInterfaceFactory
     */
    private RegionInterfaceFactory $entityDataFactory;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resource;

    /**
     * @var Data
     */
    private Data $data;

    /**
     * @param Context $context
     * @param DataPersistorInterface $dataPersistor
     * @param SaveCommand $saveCommand
     * @param RegionInterfaceFactory $entityDataFactory
     * @param ResourceConnection $resource
     * @param Data $data
     */
    public function __construct(
        Context                $context,
        DataPersistorInterface $dataPersistor,
        SaveCommand            $saveCommand,
        RegionInterfaceFactory $entityDataFactory,
        ResourceConnection     $resource,
        Data                   $data
    )
    {
        parent::__construct($context);
        $this->dataPersistor = $dataPersistor;
        $this->saveCommand = $saveCommand;
        $this->entityDataFactory = $entityDataFactory;
        $this->resource = $resource;
        $this->data = $data;
    }

    /**
     * Save SubCity Action.
     *
     * @return ResultInterface
     * @throws Exception
     */
    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $params = $this->getRequest()->getParam('general');

        try {
            /** @var RegionInterface|DataObject $entityModel */
            $entityModel = $this->entityDataFactory->create();
            $entityModel->addData($params);
            $regionId = $this->saveCommand->execute($entityModel);
            if ($entityModel->getIsDefault()) {
                $this->messageManager->addSuccessMessage(
                    __('The Region data was saved successfully (The default name of region cannot be changed).')
                );
            } else {
                $this->messageManager->addSuccessMessage(
                    __('The Region data was saved successfully')
                );
            }
            $this->dataPersistor->clear('entity');
        } catch (CouldNotSaveException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            $this->dataPersistor->set('entity', $params);

            if (empty($params[RegionInterface::REGION_ID])) {
                return $resultRedirect->setPath(
                    '*/region/new',
                    [RegionInterface::COUNTRY_ID => $params[RegionInterface::COUNTRY_ID]]
                );
            }

        }

        return $resultRedirect->setPath('*/region/edit', [
            RegionInterface::REGION_ID => $regionId ?? $params[RegionInterface::REGION_ID]
        ]);
    }
}
