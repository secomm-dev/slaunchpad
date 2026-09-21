<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Physical;

/**
 * DEC-TASK9Q5ZAK-001 — merchant default package dimensions (cm), used ONLY as an admin PREFILL
 * for the package-information input. Prefill != authoritative carrier dimensions: the values
 * that reach a carrier are the ones confirmed on the shipment (persisted physical facts).
 * Unset (0/empty) = no prefill — the admin must enter real dimensions.
 *
 * Shape mirrors the STC3NB config pattern (ConfiguredCodPaymentMethodResolver).
 */
class ConfiguredDefaultPackageDimensions
{
    public const CONFIG_PATH_DEFAULT_LENGTH = 'secomm_shippingcore/physical/default_package_length';
    public const CONFIG_PATH_DEFAULT_WIDTH = 'secomm_shippingcore/physical/default_package_width';
    public const CONFIG_PATH_DEFAULT_HEIGHT = 'secomm_shippingcore/physical/default_package_height';

    public function __construct(private readonly \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * @return array{length: int, width: int, height: int} 0 = unset (no prefill for that side)
     */
    public function getDimensions(): array
    {
        return [
            'length' => (int) $this->scopeConfig->getValue(self::CONFIG_PATH_DEFAULT_LENGTH),
            'width' => (int) $this->scopeConfig->getValue(self::CONFIG_PATH_DEFAULT_WIDTH),
            'height' => (int) $this->scopeConfig->getValue(self::CONFIG_PATH_DEFAULT_HEIGHT),
        ];
    }
}
