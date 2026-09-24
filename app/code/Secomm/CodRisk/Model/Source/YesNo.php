<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * String-valued Yes/No for UI grid select columns: the data provider returns DB
 * smallint values as strings and the select column matches strictly — int-valued
 * core options render empty cells (see ActivationStatus, same rationale).
 */
class YesNo implements OptionSourceInterface
{
    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['label' => (string)__('Yes'), 'value' => '1'],
            ['label' => (string)__('No'), 'value' => '0'],
        ];
    }
}