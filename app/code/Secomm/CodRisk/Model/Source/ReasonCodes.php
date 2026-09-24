<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\CodRisk\Model\Reason\ReasonCatalog;

/**
 * Reason code option source for config multiselect + admin forms.
 */
class ReasonCodes implements OptionSourceInterface
{
    public function __construct(
        private readonly ReasonCatalog $reasonCatalog,
    ) {
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->reasonCatalog->getReasons() as $code => $meta) {
            $options[] = [
                'label' => (string)__($meta['label']),
                'value' => $code,
            ];
        }

        return $options;
    }
}
