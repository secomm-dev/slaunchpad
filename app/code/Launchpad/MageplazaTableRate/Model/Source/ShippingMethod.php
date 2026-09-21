<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — dynamic fallback-member options.
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Model\Source;

use Magento\Shipping\Model\Config as ShippingConfig;

/**
 * Options derive from the REAL Magento carrier configuration at runtime — never a hardcoded
 * carrier list (directive §16). Optional carrier add-ons that are not installed simply do not
 * appear; the `mptablerate` carrier itself is excluded (a table-rate method can never be its
 * own fallback member — recursion guard). Values use the `carrier_code||method_code` pair
 * encoding (stable Magento shipping-method identity, no underscore composite).
 */
class ShippingMethod implements \Magento\Framework\Data\OptionSourceInterface
{
    /** Separator for the (carrier_code, method_code) pair inside a single select value. */
    public const PAIR_SEPARATOR = '||';

    private const SELF_CARRIER_CODE = 'mptablerate';

    public function __construct(
        private readonly ShippingConfig $shippingConfig
    ) {
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->shippingConfig->getAllCarriers() as $carrier) {
            $carrierCode = (string) $carrier->getCarrierCode();
            if ($carrierCode === '' || $carrierCode === self::SELF_CARRIER_CODE) {
                continue;
            }

            $carrierLabel = (string) ($carrier->getConfigData('title') ?: $carrierCode);
            foreach ($carrier->getAllowedMethods() ?? [] as $methodCode => $methodTitle) {
                $methodCode = (string) $methodCode;
                if ($methodCode === '') {
                    continue;
                }

                $options[] = [
                    'value' => $carrierCode . self::PAIR_SEPARATOR . $methodCode,
                    'label' => $carrierLabel . ' — ' . ((string) $methodTitle ?: $methodCode),
                ];
            }
        }

        usort($options, static fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $options;
    }
}
