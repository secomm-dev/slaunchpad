<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\CodRisk\Api\Data\CodRiskDecisionInterface;

class Decisions implements OptionSourceInterface
{
    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['label' => (string)__('WARNING'), 'value' => CodRiskDecisionInterface::WARNING],
            ['label' => (string)__('BLOCK'), 'value' => CodRiskDecisionInterface::BLOCK],
        ];
    }
}
