<?php declare(strict_types=1);

namespace Secomm\GiaoHangNhanh\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class SyncMode implements OptionSourceInterface
{
    public const SYNC_MODE_ASYNC = 'async';
    public const SYNC_MODE_DIRECT = 'direct';

    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::SYNC_MODE_ASYNC, 'label' => __('Async (Message Queue)')],
            ['value' => self::SYNC_MODE_DIRECT, 'label' => __('Direct (Immediate)')],
        ];
    }
}
