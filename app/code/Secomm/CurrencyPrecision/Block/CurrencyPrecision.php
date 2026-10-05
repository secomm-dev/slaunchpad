<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CurrencyPrecision\Block;

use Magento\Framework\View\Element\Template;
use Secomm\CurrencyPrecision\Model\PrecisionResolver\Proxy;

/**
 * Renders the resolved precision for the current store + display currency into
 * the Hyvä formatPrice wrapper script (server decides, JS never re-derives —
 * single source of truth keeps server-rendered and dynamic prices identical).
 */
class CurrencyPrecision extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Proxy $precisionResolver,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Fixed precision for the current display currency, or null for Auto
     * (null renders nothing — the native formatter stays untouched).
     */
    public function getPrecision(): ?int
    {
        return $this->precisionResolver->resolveCurrent();
    }
}
