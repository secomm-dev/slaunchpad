<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Controller\Adminhtml\Zone;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Secomm\ShippingCore\Model\Zone as ZoneModel;
use Secomm\ShippingCore\Model\ResourceModel\Zone as ZoneResource;
use Secomm\ShippingCore\Model\ZoneFactory;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — create/edit form. The loaded zone model lives in the
 * backend registry for the form data provider and the buttons (Magento-standard admin CRUD
 * pattern — mutations still go through the CanonicalZoneRepository).
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_ShippingCore::zones_manage';

    public const REGISTRY_KEY = 'secomm_shippingcore_zone';

    private PageFactory $resultPageFactory;

    private \Magento\Framework\Registry $registry;

    private ZoneFactory $zoneFactory;

    private ZoneResource $zoneResource;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        \Magento\Framework\Registry $registry,
        ZoneFactory $zoneFactory,
        ZoneResource $zoneResource
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->registry = $registry;
        $this->zoneFactory = $zoneFactory;
        $this->zoneResource = $zoneResource;
    }

    /**
     * @return Page|Redirect|ResultInterface
     */
    public function execute()
    {
        $zoneId = (int) $this->getRequest()->getParam('zone_id');
        $zone = $this->zoneFactory->create();
        if ($zoneId) {
            $this->zoneResource->load($zone, $zoneId);
            if (!$zone->getId()) {
                $this->messageManager->addErrorMessage(__('This zone no longer exists.'));
                $redirect = $this->resultRedirectFactory->create();
                $redirect->setPath('*/*/index');

                return $redirect;
            }
        }
        $this->registry->register(self::REGISTRY_KEY, $zone, true);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Secomm_ShippingCore::zones');
        $resultPage->getConfig()->getTitle()->prepend(
            $zone->getId() ? __('Edit Shipping Zone') : __('New Shipping Zone')
        );

        return $resultPage;
    }
}
