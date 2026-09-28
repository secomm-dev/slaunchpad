<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\AddressDropdown\Plugin\Checkout;

use Magento\Checkout\Model\DefaultConfigProvider;
use Secomm\AddressDropdown\Helper\Data as AddressDropdownHelper;

/**
 * TASK-SEC-A5 — exposes the store-scoped master switch to storefront JS via
 * window.checkoutConfig.secommAddressDropdownEnabled. Every RequireJS mixin and dropdown
 * component gates itself on this flag so a disabled module behaves exactly like an absent
 * one (native Magento address fields, no AJAX/GraphQL, no DOM mutation).
 */
class DefaultConfigProviderPlugin
{
    public function __construct(
        private readonly AddressDropdownHelper $addressDropdownHelper
    ) {
    }

    /**
     * @param DefaultConfigProvider $subject
     * @param array $result
     * @return array
     */
    public function afterGetConfig(DefaultConfigProvider $subject, array $result): array
    {
        $result['secommAddressDropdownEnabled'] = $this->addressDropdownHelper->isAddressDropdownModuleEnabled();

        return $result;
    }
}
