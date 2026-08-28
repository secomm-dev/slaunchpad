<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\InventoryApi\Api\SourceRepositoryInterface;

class MagentoSources implements OptionSourceInterface
{
    public function __construct(
        private readonly SourceRepositoryInterface $sourceRepository
    ) {
    }

    public function toOptionArray(): array
    {
        $options = [];
        try {
            $list = $this->sourceRepository->getList();
            foreach ($list->getItems() as $source) {
                $code = (string) $source->getSourceCode();
                if ($code === '') {
                    continue;
                }
                $name = (string) $source->getName();
                $options[] = [
                    'value' => $code,
                    'label' => $name !== '' ? $name . ' (' . $code . ')' : $code,
                ];
            }
        } catch (\Throwable) {
            return [['value' => 'default', 'label' => 'default']];
        }

        usort($options, static fn (array $a, array $b): int => strcmp((string) $a['label'], (string) $b['label']));
        return $options;
    }
}
