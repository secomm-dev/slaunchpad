<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Plugin\Model\Checks;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\Checks\TotalMinMax;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;
use Secomm\ZaloPay\Gateway\Helper\Rate;

/**
 * Plugin to handle ZaloPay currency conversion in TotalMinMax check
 */
class TotalMinMaxPlugin
{
    /**
     * @var Rate
     */
    private Rate $rate;

    /**
     * ZaloPay method code
     */
    private const ZALO_PAY_METHOD_CODE = "zalopay";

    /**
     * Minimum order total config key
     */
    private const MIN_ORDER_TOTAL = "min_order_total";

    /**
     * Maximum order total config key
     */
    private const MAX_ORDER_TOTAL = "max_order_total";

    /**
     * Constructor
     *
     * @param Rate $rate
     */
    public function __construct(Rate $rate)
    {
        $this->rate = $rate;
    }

    /**
     * Around plugin to handle currency conversion for ZaloPay
     *
     * Converts the quote total to VND before comparing against configured min/max
     * order totals. When no min/max is configured, or currency conversion is not
     * possible (no exchange rate set up), the method is allowed rather than
     * blocking checkout.
     *
     * @param TotalMinMax $subject
     * @param callable $proceed
     * @param MethodInterface $paymentMethod
     * @param Quote $quote
     * @return bool
     */
    public function aroundIsApplicable(
        TotalMinMax $subject,
        callable $proceed,
        MethodInterface $paymentMethod,
        Quote $quote
    ): bool {
        // Only apply for ZaloPay
        if ($paymentMethod->getCode() !== self::ZALO_PAY_METHOD_CODE) {
            return $proceed($paymentMethod, $quote);
        }

        $minTotal = $paymentMethod->getConfigData(self::MIN_ORDER_TOTAL);
        $maxTotal = $paymentMethod->getConfigData(self::MAX_ORDER_TOTAL);

        // No min/max configured -> nothing to validate, allow the method.
        if (empty($minTotal) && empty($maxTotal)) {
            return true;
        }

        // Convert to VND for ZaloPay. If conversion fails (no rate configured),
        // fall back to allowing the method instead of breaking checkout.
        try {
            $total = $this->rate->getVndAmountByCurrency(
                $quote->getBaseCurrencyCode(),
                $quote->getBaseGrandTotal()
            );
        } catch (LocalizedException $e) {
            return true;
        }

        if ((float)$minTotal > 0 && $total < (float)$minTotal
            || (float)$maxTotal > 0 && $total > (float)$maxTotal
        ) {
            return false;
        }
        return true;
    }
}

