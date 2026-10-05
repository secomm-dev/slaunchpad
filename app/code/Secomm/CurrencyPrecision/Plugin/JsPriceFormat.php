<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CurrencyPrecision\Plugin;

use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Exception\State\StateException;
use Magento\Framework\Locale\Format;
use Secomm\CurrencyPrecision\Model\PrecisionResolver\Proxy;

/**
 * Syncs the configured precision into the JS price format consumed by
 * priceUtils / checkoutConfig.priceFormat (Luma fallback + Mageplaza OSC).
 * precision and requiredPrecision move together so fixed decimals always
 * render (e.g. 4 -> ",0000") exactly like the server-rendered price.
 */
class JsPriceFormat
{
    public function __construct(
        private readonly AppState $appState,
        private readonly Proxy $resolver,
    ) {
    }

    /**
     * @param array $result price format with precision/requiredPrecision keys
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetPriceFormat(
        Format $subject,
        array $result,
        $localeCode = null,
        $currencyCode = null
    ): array {
        if ($this->isBackendArea()) {
            return $result;
        }

        $precision = ($currencyCode !== null && (string)$currencyCode !== '')
            ? $this->resolver->resolve((string)$currencyCode)
            : $this->resolver->resolveCurrent();
        if ($precision === null) {
            return $result;
        }

        $result['precision'] = $precision;
        $result['requiredPrecision'] = $precision;

        return $result;
    }

    private function isBackendArea(): bool
    {
        try {
            return $this->appState->getAreaCode() === Area::AREA_ADMINHTML;
        } catch (StateException) {
            return false;
        }
    }
}
