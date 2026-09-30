<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * FEAT-QA23PZ / TASK-G3K9V2 — pointer note where the destination-scope / zone / rate
 * orchestration fields used to live: they now live in the shared Secomm → Shipping Coverage
 * screen (ShippingCore owns the carrier-coverage UX). Config paths are unchanged, so the
 * persisted values stay effective for the runtime readers.
 */
class CoveragePointer extends Field
{
    /**
     * @inheritDoc
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $url = $this->getUrl('secomm_shippingcore/coverage/index', [
            'target_type' => 'CARRIER',
            'target_code' => 'secomm_ghn',
        ]);

        return (string) __(
            'Configured in <a href="%1">Secomm → Shipping → Shipping Coverage</a> — availability (service area), allowed zones, Rate Source Mode and Address Resolution Policy. Values persist to the same configuration paths as before.',
            $url
        );
    }
}
