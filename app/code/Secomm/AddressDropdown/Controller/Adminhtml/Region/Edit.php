<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Controller\Adminhtml\Region;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Secomm\AddressDropdown\Helper\Data;

/**
 * Edit Region entity backend controller.
 */
class Edit extends Action implements HttpGetActionInterface
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
    private DataPersistorInterface $dataPersistor;

    /**
     * @var Data
     */
    private Data $data;

    /**
     * @param Context $context
     * @param DataPersistorInterface $dataPersistor
     * @param Data $data
     */
    public function __construct(
        Context                $context,
        DataPersistorInterface $dataPersistor,
        Data                   $data,
    )
    {
        $this->dataPersistor = $dataPersistor;
        $this->data = $data;
        parent::__construct($context);
    }

    /**
     * Edit Region action.
     *
     */
    public function execute()
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Secomm_AddressDropdown::management');
        $resultPage->getConfig()->getTitle()->prepend(__('Edit States/Provinces'));

        $regionId = $this->_request->getParam('region_id');
        $countryId = $this->data->getCountryIdByRegionId($regionId);
        if (empty($countryId)) {
            return $this->_redirect('addressdropdown/country/index');;
        }

        return $resultPage;
    }
}
