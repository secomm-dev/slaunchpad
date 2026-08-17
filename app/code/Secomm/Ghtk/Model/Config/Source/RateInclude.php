<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Selectable fee components added on top of the base fee.fee to form the
 * displayed shipping amount (DEC-022 Q1). Base fee.fee is always included.
 */
class RateInclude implements OptionSourceInterface
{
    public const INSURANCE_FEE = 'insurance_fee';
    public const EXT_FEES = 'extFees';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::INSURANCE_FEE, 'label' => __('Insurance Fee')],
            ['value' => self::EXT_FEES, 'label' => __('Extended Fees (surcharge)')],
        ];
    }
}
