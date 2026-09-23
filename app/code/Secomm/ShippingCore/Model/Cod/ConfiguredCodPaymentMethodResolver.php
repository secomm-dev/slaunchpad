<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Cod;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Secomm\ShippingCore\Api\Cod\CodPaymentMethodResolverInterface;

/**
 * TASK-STC3NB (address-shipping architecture Revision v4 §4.1) — configuration-backed COD
 * payment method resolver; @see CodPaymentMethodResolverInterface.
 *
 * The COD payment method codes are DECLARED in configuration (never hardcoded): a single
 * comma-separated list at `secomm_shippingcore/cod/payment_methods`
 * (e.g. "cashondelivery, custom_cod"). Comparison is EXACT and case-sensitive after trimming —
 * a prefix or differently-cased code is NOT a match, and an unknown/unconfigured code (or an
 * empty/malformed config) is simply `false`. No Magento COD implementation is referenced and
 * no default COD method exists: silence in configuration means "nothing is COD".
 *
 * Scope: read at the default config scope — the Launchpad deployment is a single store group;
 * store-scoped COD lists would be an additive upgrade that does not change this contract.
 */
final class ConfiguredCodPaymentMethodResolver implements CodPaymentMethodResolverInterface
{
    public const CONFIG_PATH_PAYMENT_METHODS = 'secomm_shippingcore/cod/payment_methods';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isCod(string $paymentMethodCode): bool
    {
        $configured = $this->scopeConfig->getValue(self::CONFIG_PATH_PAYMENT_METHODS);
        if (!is_string($configured) || trim($configured) === '') {
            return false;
        }

        $codMethods = array_filter(
            array_map(static fn (string $code): string => trim($code), explode(',', $configured)),
            static fn (string $code): bool => $code !== ''
        );

        return in_array(trim($paymentMethodCode), $codMethods, true);
    }
}
