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
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * City backend index (list) controller.
 */
class Index extends Action implements HttpGetActionInterface
{
    public function __construct(
        protected DataPersistorInterface $dataPersistor,
        Context                          $context
    )
    {
        parent::__construct($context);
    }
    /**
     * Authorization level of a basic admin session.
     */
    public const ADMIN_RESOURCE = 'Secomm_AddressDropdown::management';

    /**
     * Execute action based on request and return result.
     *
     * @return ResultInterface|ResponseInterface
     */
    public function execute()
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $regionId = $this->_request->getParam('region_id');
        $this->dataPersistor->clear('entity');
        if ($regionId) {
            $this->dataPersistor->set('region_id', $regionId);
        }

        $resultPage->setActiveMenu('Secomm_AddressDropdown::management');
        $resultPage->addBreadcrumb(__('City'), __('City'));
        $resultPage->addBreadcrumb(__('Manage Cities'), __('Manage Cities'));
        $resultPage->getConfig()->getTitle()->prepend(__('City List'));

        return $resultPage;
    }
}
