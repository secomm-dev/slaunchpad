<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Plugin\Frontend;

use Magento\Customer\Block\Address\Edit;
use Secomm\AddressDropdown\Helper\Data;
use Secomm\AddressDropdown\Model\OptionSource\RendererMode;

/**
 * FEAT-2PZQKJ / TASK-3T3NSV — per-store A/B cutover for the Hyva customer address form:
 * when the module is enabled, `address/general/renderer` = schema AND the layout resolved
 * the module's Hyva legacy template (only set by the `hyva_customer_address_form` handle,
 * which Hyva themes load exclusively), swap it for the schema-driven renderer.
 *
 * Theme gate (audit 2026-08-28): the swap fires ONLY on the Hyva legacy template — a Luma
 * storefront renders `Secomm_AddressDropdown::address/edit.phtml` (jQuery cascade) and is
 * never swapped, so enabling the flag on a Luma store cannot break its form. Any template
 * other than the two known ones is left untouched (a child theme overriding the legacy
 * Hyva template opts out of the auto-swap).
 */
class CustomerAddressEditTemplate
{
    public const LEGACY_HYVA_TEMPLATE = 'Secomm_AddressDropdown::hyva/address/edit.phtml';
    public const SCHEMA_TEMPLATE = 'Secomm_AddressDropdown::hyva/address/schema-edit.phtml';

    public function __construct(
        private readonly Data $addressDropdownHelper
    ) {
    }

    /**
     * @param Edit $subject
     * @param string $result
     * @return string
     */
    public function afterGetTemplate(Edit $subject, string $result): string
    {
        if (!$this->addressDropdownHelper->isAddressDropdownModuleEnabled()) {
            return $result;
        }

        $renderer = (string)$this->addressDropdownHelper->getConfigValue('address/general/renderer');
        if ($renderer !== RendererMode::MODE_SCHEMA) {
            return $result;
        }

        if ($result !== self::LEGACY_HYVA_TEMPLATE) {
            return $result;
        }

        return self::SCHEMA_TEMPLATE;
    }
}
