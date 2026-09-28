<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Controller\Adminhtml\Coverage;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * FEAT-QA23PZ / TASK-WY6WP5 — Shipping Coverage overview grid: every registered coverage
 * target (P1: carriers) with its configuration status + effective availability + zones.
 * The single editing surface for target ↔ zone assignment (carrier modules no longer own
 * that UX — directive §7/§20). Rows come from the CoverageTargetRegistry (registration ≠
 * persisted config); targets without explicit config run on the documented defaults.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_ShippingCore::carrier_coverage';

    private PageFactory $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Secomm_ShippingCore::carrier_coverage');
        $resultPage->getConfig()->getTitle()->prepend(__('Shipping Coverage'));

        return $resultPage;
    }
}
