<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Block\Adminhtml\Shipment;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Secomm\Ghn\ViewModel\PackageInformationViewModel;

/**
 * TASK-9Q5ZAK r2 — "Package Information" section on the admin new-shipment page (hosted in the
 * `extra_shipment_info` container). The confirmed values post as `shipment[physical_packages]`
 * and are the ONLY physical source the GHN create uses.
 */
class PackageInformation extends Template
{
    private PackageInformationViewModel $viewModel;

    public function __construct(
        Context $context,
        PackageInformationViewModel $viewModel,
        array $data = []
    ) {
        $this->viewModel = $viewModel;
        parent::__construct($context, $data);
    }

    public function getViewModel(): PackageInformationViewModel
    {
        return $this->viewModel;
    }
}
