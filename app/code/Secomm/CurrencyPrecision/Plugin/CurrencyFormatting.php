<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CurrencyPrecision\Plugin;

use Magento\Directory\Model\Currency;
use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Exception\LocalizedException;
use Secomm\CurrencyPrecision\Model\PrecisionResolver\Proxy;

/**
 * Injects the configured display precision into every server-rendered price.
 *
 * formatTxt() is the single choke point of Currency (format() ->
 * formatPrecision() -> formatTxt()), and $options['precision'] drives both the
 * Intl NumberFormatter path and the legacy toCurrency fallback. Display only —
 * numeric values, tax/discount math, convertAndRound() and payment payloads
 * never pass through formatTxt.
 *
 * Per approved decision D-3 the caller-supplied precision is always overridden;
 * per D-4 (default, pending confirmation) the adminhtml area keeps native
 * formatting — order emails/PDF still follow the config because they render
 * under frontend area emulation.
 */
class CurrencyFormatting
{
    public function __construct(
        private readonly AppState $appState,
        private readonly Proxy $resolver,
    ) {
    }

    /**
     * @return array|null null = leave the original call untouched (Auto/backend)
     */
    public function beforeFormatTxt(Currency $subject, $price, array $options = []): ?array
    {
        if ($this->isBackendArea()) {
            return null;
        }

        $precision = $this->resolver->resolve((string)$subject->getCode());
        if ($precision === null) {
            return null;
        }

        $options['precision'] = $precision;

        return [$price, $options];
    }

    private function isBackendArea(): bool
    {
        try {
            return $this->appState->getAreaCode() === Area::AREA_ADMINHTML;
        } catch (LocalizedException) {
            // getAreaCode() throws LocalizedException when the area is not set
            // (CLI/setup) — treat as frontend so export/CLI formatting stays
            // consistent with storefront display.
            return false;
        }
    }
}
