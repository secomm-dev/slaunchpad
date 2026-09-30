<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — Mageplaza method Tabs + the Fallback Settings tab.
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
        // TASK-RT50KH FIX — the tab MUST be added BEFORE parent::_beforeToHtml(): the core
        // Widget\Tabs::_beforeToHtml() ends with `assign('tabs', $this->_tabs)`, which SNAPSHOTS
        // the tab list into the template data. Adding the tab after that call (the original
        // implementation) mutates $_tabs but never reaches the rendered template — the tab
        // silently disappeared (visible as "only 3 tabs" in the method edit page).
        if ($this->getChildBlock('launchpad')) {
            $tab = [
                'label' => __('Fallback Settings'),
                'title' => __('Fallback Settings'),
                'content' => $this->getChildHtml('launchpad'),
            ];
            // Position after "Shipping Rates" when that tab exists (edit of a saved method);
            // on the create page the plain addTab keeps Fallback Settings as the last tab.
            if ($this->getChildBlock('rate')) {
                $this->addTabAfter('launchpad', $tab, 'rate');
            } else {
                $this->addTab('launchpad', $tab);
            }
        }

        return parent::_beforeToHtml();
    }
}
