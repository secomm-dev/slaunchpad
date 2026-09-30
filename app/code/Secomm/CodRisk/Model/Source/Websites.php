<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Website option source (0 = All Websites) for grid filters.
 */
class Websites implements OptionSourceInterface
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function toOptionArray(): array
    {
        $options = [['label' => (string)__('All Websites'), 'value' => '0']];
        foreach ($this->storeManager->getWebsites() as $website) {
            $options[] = [
                'label' => (string)$website->getName(),
                'value' => (string)(int)$website->getId(),
            ];
        }

        return $options;
    }
}