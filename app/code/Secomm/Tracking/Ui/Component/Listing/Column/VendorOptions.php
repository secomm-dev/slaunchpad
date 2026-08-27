<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * FEAT-31X6N2 — vendor filter options for the admin grids.
 */
class VendorOptions implements OptionSourceInterface
{
    /**
     * Meta removed (DEC-FEAT31X6N2-002): browser pixel + CAPI handled by the
     * licensed Magefan FacebookPixel(+Extra) suite — this module only owns
     * TikTok now. Historical meta rows may still exist in the tables/grid.
     *
     * @return array<int, array<string, string>>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'tiktok', 'label' => __('TikTok')],
        ];
    }
}
