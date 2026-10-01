<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Block\Adminhtml\Shipment;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Secomm\ShippingCore\ViewModel\Shipment\OfflineControl;

/**
 * TASK-S52DGA (DEC-TASKS52DGA-001) — "Create Offline Shipment" control on the admin new-shipment
 * page (hosted in the core `submit_after` container). Lives INSIDE the native `#edit_form`: the
 * offline intent posts through the CORE save endpoint with the form (`shipment[fulfillment_mode]`,
 * armed by the template's JS only when the offline button is used) — no dedicated endpoint, no
 * new ACL (native Magento_Sales::ship applies).
 */
class NewOfflineControl extends Template
{
    private OfflineControl $viewModel;

    public function __construct(
        Context $context,
        OfflineControl $viewModel,
        array $data = []
    ) {
        $this->viewModel = $viewModel;
        parent::__construct($context, $data);
    }

    public function getViewModel(): OfflineControl
    {
        return $this->viewModel;
    }
}
