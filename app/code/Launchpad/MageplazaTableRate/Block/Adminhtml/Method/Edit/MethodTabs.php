<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — Mageplaza method Tabs + the Launchpad Settings tab.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Block\Adminhtml\Method\Edit;

use Mageplaza\TableRateShipping\Block\Adminhtml\Method\Edit\Tabs as MageplazaTabs;

/**
 * Preference subclass of the Mageplaza tabs block: appends the Launchpad tab (content comes
 * from the layout child block `launchpad`). Preference — not source edit — keeps Mageplaza
 * upgrades safe; removing this preference restores native tabs untouched.
 */
class MethodTabs extends MageplazaTabs
{
    protected function _beforeToHtml()
    {
        parent::_beforeToHtml();

        if (!$this->getChildBlock('launchpad')) {
            return $this;
        }

        $this->addTab('launchpad', [
            'label' => __('Launchpad Settings'),
            'title' => __('Launchpad Settings'),
            'content' => $this->getChildHtml('launchpad'),
        ]);

        return $this;
    }
}
