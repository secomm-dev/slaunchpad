<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Controller\Adminhtml\SubCity;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Secomm\AddressDropdown\Helper\Data;

/**
 * Edit SubCity entity backend controller.
 */
class Edit extends Action implements HttpGetActionInterface
{
    /**
     * @var Data
     */
    private Data $data;

    /**
     * @param Data $data
     * @param Context $context
     */
    public function __construct(
        Data    $data,
        Context $context)
    {
        $this->data = $data;
        parent::__construct($context);
    }

    /**
     * Authorization level of a basic admin session.
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Secomm_AddressDropdown::management';

    /**
     * Edit SubCity action.
     *
     * @return Page|ResultInterface
     */
    public function execute()
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Secomm_AddressDropdown::management');
        $resultPage->getConfig()->getTitle()->prepend(__('Edit SubCity'));

        $subCityId = $this->_request->getParam('sub_city_id');
        $cityId = $this->data->getCityIdBySubCityId($subCityId);
        if (!$cityId) {
            return $this->_redirect('addressdropdown/country/index');;
        }
        return $resultPage;
    }
}
