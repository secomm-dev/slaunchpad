<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Controller\Adminhtml\Zone;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — create forwards to the shared edit form (no zone
 * loaded); the forward keeps the layout handle consistent with the edit form.
 */
class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_ShippingCore::zones_manage';

    public function execute()
    {
        $this->_forward('edit');
    }
}
