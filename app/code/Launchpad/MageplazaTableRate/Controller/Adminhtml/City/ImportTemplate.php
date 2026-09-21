<?php
/*
 * TASK-JZXM66 — TableRate import template download: the importer's exact column superset plus
 * the informational `city_name` column, pre-filled with a worked example row + wildcard row.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Controller\Adminhtml\City;

use Launchpad\MageplazaTableRate\Controller\Adminhtml\City;
use Launchpad\MageplazaTableRate\Model\Adminhtml\ImportTemplateBuilder;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;

/**
 * GET launchpad_mptablerate/city/importTemplate[?region=<region_id>] →
 * tablerate_import_template.csv (UTF-8 + BOM). Optional region filter prefills the example
 * row from that region.
 */
class ImportTemplate extends City
{
    public function __construct(
        Context $context,
        private readonly ImportTemplateBuilder $templateBuilder
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $regionParam = $this->getRequest()->getParam('region');
        $regionId = is_numeric($regionParam) ? (int) $regionParam : 0;

        return $this->csvDownload(
            'tablerate_import_template.csv',
            $this->templateBuilder->build($regionId > 0 ? $regionId : null)
        );
    }
}
