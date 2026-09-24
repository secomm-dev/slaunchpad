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
 * String-valued options on purpose: the grid data provider returns DB values as
 * strings ("1"/"0") and the UI select column matches strictly — int-valued
 * options (e.g. core Yesno) render as empty cells.
 */
class ActivationStatus implements OptionSourceInterface
{
    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['label' => (string)__('Active'), 'value' => '1'],
            ['label' => (string)__('Inactive'), 'value' => '0'],
        ];
    }
}