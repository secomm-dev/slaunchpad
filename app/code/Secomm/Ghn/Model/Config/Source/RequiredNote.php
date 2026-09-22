<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * SPEC-FEAT-FQWEQ3 §36 — preserve legacy business behavior (required_note codes as used by the
 * legacy Secomm_GiaoHangNhanh integration; default CHOXEMHANGKHONGTHU).
 */
class RequiredNote implements OptionSourceInterface
{
    public const NOT_ALLOWED_VIEWING = 'CHOXEMHANGKHONGTHU';
    public const ALLOWED_VIEWING = 'CHOXEMHANG';
    public const ALLOWED_TESTING = 'CHOTHUHANG';
    public const ALLOWED_TESTING_NO_REFUND = 'CHOTHUHANGKHONGDOI';

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::NOT_ALLOWED_VIEWING, 'label' => __('Not allow viewing (default)')],
            ['value' => self::ALLOWED_VIEWING, 'label' => __('Allow viewing')],
            ['value' => self::ALLOWED_TESTING, 'label' => __('Allow testing (refund supported)')],
            ['value' => self::ALLOWED_TESTING_NO_REFUND, 'label' => __('Allow testing (no refund)')],
        ];
    }
}
