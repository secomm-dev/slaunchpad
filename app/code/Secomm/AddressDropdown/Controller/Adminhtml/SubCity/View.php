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
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Secomm\AddressDropdown\Api\Data\CityInterface;

/**
 * SubCity backend index (list) controller.
 */
class View extends Action implements HttpGetActionInterface
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

        $resultPage->setActiveMenu('Secomm_AddressDropdown::management');
        $resultPage->addBreadcrumb(__('SubCity'), __('SubCity'));
        $resultPage->addBreadcrumb(__('Manage SubCitys'), __('Manage SubCitys'));
        $resultPage->getConfig()->getTitle()->prepend(__('SubCity List'));

        return $resultPage;
    }
}
