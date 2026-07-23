<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Controller\Adminhtml\City;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Secomm\AddressDropdown\Helper\Data;

/**
 * Edit City entity backend controller.
 */
class Edit extends Action implements HttpGetActionInterface
{
    /**
     * @var Data
     */
    private Data $data;

    public function __construct(
        Data    $data,
        Context $context
    )
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
     * Edit City action.
     *
     * @return Page|ResultInterface
     */
    public function execute()
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('Secomm_AddressDropdown::management');
        $resultPage->getConfig()->getTitle()->prepend(__('Edit City'));

        $cityId = $this->_request->getParam('city_id');
        $regionId = $this->data->getRegionIdByCityId($cityId);
        if (!$regionId) {
            return $this->_redirect('addressdropdown/country/index');;
        }

        return $resultPage;
    }
}
