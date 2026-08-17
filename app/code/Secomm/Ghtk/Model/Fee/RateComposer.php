<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Fee;

use Secomm\Ghtk\Model\Config\Source\RateInclude;

/**
 * Composes the displayed shipping amount (DEC-022 Q1): base fee.fee always included,
 * plus insurance_fee and/or extFees when selected in config (multi-select). Never
 * silently drops a component — unselected components are simply not added to the
 * displayed amount (their full breakdown is still logged masked by the caller).
 */
class RateComposer
{
    public function compose(FeeResult $fee, array $include): float
    {
        $amount = $fee->fee;

        if (in_array(RateInclude::INSURANCE_FEE, $include, true)) {
            $amount += $fee->insuranceFee;
        }
        if (in_array(RateInclude::EXT_FEES, $include, true)) {
            $amount += $fee->extFees;
        }

        return round(max(0.0, $amount), 4);
    }
}
