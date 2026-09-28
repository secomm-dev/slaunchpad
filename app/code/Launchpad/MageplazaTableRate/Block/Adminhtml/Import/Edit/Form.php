<?php
/*
 * TASK-RT50KH — Launchpad import form: the sample-file link points to the Launchpad
 * Import Template (20-column schema including the optional `city_code` + informational
 * `city_name`) instead of Mageplaza's Google Drive folder sample, whose schema predates
 * the City / Area columns — a merchant filling that sample silently loses the City / Area
 * assignment on import (the importer drops unknown/absent columns by header name).
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Block\Adminhtml\Import\Edit;

use Mageplaza\TableRateShipping\Block\Adminhtml\Import\Edit\Form as MageplazaImportForm;

/**
 * Preference subclass (vendor file untouched — same pattern as MethodTabs/CityForm/CityGrid).
 * Only the sample-file link changes: the upload target (`mptablerate/import/process`) and the
 * importer (MptablerateImport) are untouched.
 */
class Form extends MageplazaImportForm
{
    /**
     * The Launchpad Import Template is generated server-side from the importer's own column
     * superset (MptablerateImport::templateColumns + a worked example with a real City / Area
     * code), so it is importable as-is.
     */
    protected function _getDownloadSampleFileHtml(): string
    {
        $url = $this->getUrl('launchpad_mptablerate/city/importTemplate');

        return sprintf(
            '<span><a href="%s" target="_blank">%s</a></span>',
            $this->escapeHtmlAttr($url),
            __('Download Import Template (includes City / Area columns)')
        );
    }
}
