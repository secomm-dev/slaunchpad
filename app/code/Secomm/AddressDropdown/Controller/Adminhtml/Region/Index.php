<?php

namespace Secomm\AddressDropdown\Controller\Adminhtml\Region;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Backend\App\Action\Context;

class Index extends Action implements HttpGetActionInterface
{
    /**
     * @param DataPersistorInterface $dataPersistor
     * @param Context $context
     */
    public function __construct(
        protected DataPersistorInterface $dataPersistor,
        Context                          $context
    )
    {
        parent::__construct($context);
    }

    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'Secomm_AddressDropdown::listing';

    /**
     * Execute action based on request and return result
     *
     * @return ResultInterface|ResponseInterface
     * @throws NotFoundException
     */
    public function execute()
    {
        $countryId = $this->_request->getParam('country_id');
        if ($countryId) {
            $this->dataPersistor->set('country_id', $countryId);
        }
        $this->dataPersistor->clear('entity');

        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->prepend((__("States/Provinces")));

        return $this->resultFactory->create(ResultFactory::TYPE_PAGE);
    }
}
