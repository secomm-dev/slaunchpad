<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Controller\Adminhtml\SubCity;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use Secomm\AddressDropdown\Api\Data\SubCityInterface;
use Secomm\AddressDropdown\Command\SubCity\DeleteByIdCommand;
use Secomm\AddressDropdown\Helper\Data;

/**
 * Delete SubCity controller.
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
     * @var DataPersistorInterface
     */
    protected DataPersistorInterface $dataPersistor;
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
        Context                $context,
        DataPersistorInterface $dataPersistor,
        DeleteByIdCommand      $deleteByIdCommand,
        Data                   $data
    )
    {
        parent::__construct($context);
        $this->dataPersistor = $dataPersistor;
        $this->deleteByIdCommand = $deleteByIdCommand;
        $this->data = $data;
    }

    /**
     * Delete SubCity action.
     *
     * @return ResultInterface
     */
    public function execute()
    {
        /** @var ResultInterface $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $entityId = (int)$this->getRequest()->getParam(SubCityInterface::SUB_CITY_ID);
        $cityId = (int)$this->data->getCityIdBySubCityId($entityId);
        $resultRedirect->setPath('*/subcity/index', ['city_id' => $cityId]);

        try {
            $this->deleteByIdCommand->execute($entityId);
            $this->messageManager->addSuccessMessage(__('You have successfully deleted SubCity entity'));
        } catch (CouldNotDeleteException|NoSuchEntityException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $resultRedirect;
    }
}
