<?php
/*
 * TASK-JZXM66 — City Reference CSV download: the live city/area identity list admins use to
 * fill TableRate import files (`city_code` is the portable identity, `city_name` reference).
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Controller\Adminhtml\City;

use Launchpad\MageplazaTableRate\Controller\Adminhtml\City;
use Launchpad\MageplazaTableRate\Model\Adminhtml\CityReferenceBuilder;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;

/**
 * GET launchpad_mptablerate/city/referenceCsv[?region=<region_id>] →
 * city_reference.csv (UTF-8 + BOM). Optional region filter narrows the export.
 */
class ReferenceCsv extends City
{
    public function __construct(
        Context $context,
        private readonly CityReferenceBuilder $referenceBuilder
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $regionParam = $this->getRequest()->getParam('region');
        $regionId = is_numeric($regionParam) ? (int) $regionParam : 0;

        return $this->csvDownload(
            'city_reference.csv',
            $this->referenceBuilder->build($regionId > 0 ? $regionId : null)
        );
    }
}
