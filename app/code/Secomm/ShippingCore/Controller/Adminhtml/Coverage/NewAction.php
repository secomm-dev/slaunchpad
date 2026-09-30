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

/**
 * TASK-WY6WP5 — ADD COVERAGE entry point (directive §6). Forwards to the edit page in
 * create mode: no target_code → the form offers every registered CARRIER target that does
 * not yet have an explicit coverage config (Applies To is fixed to Carrier in P1).
 */
class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_ShippingCore::carrier_coverage_manage';

    public function execute()
    {
        $this->_forward('edit');
    }
}
