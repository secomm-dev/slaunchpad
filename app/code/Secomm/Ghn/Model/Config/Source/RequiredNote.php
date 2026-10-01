<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * TASK-4P33TV — SINGLE SOURCE OF TRUTH for the GHN `required_note` enum.
 *
 * The 3 values below are SANDBOX-VERIFIED against the create-order API (shop 200537,
 * 2026-09-30): each accepted; the two legacy-source tokens `CHOXEMHANG` and
 * `CHOTHUHANGKHONGDOI` were REJECTED by the provider ("Sai thông tin đầu vào") and are
 * removed — they made the admin dropdown offer values the create request builder
 * fail-closed on (INVALID_CONFIGURATION, shipment 21).
 *
 * Consumers MUST reference these constants (`GhnCreateRequestBuilder::REQUIRED_NOTES`)
 * — never re-declare the enum locally (drift = uncreatable shipments).
 */
class RequiredNote implements OptionSourceInterface
{
    /** Khách hàng KHông được mở kiện xem hàng (merchant default). */
    public const NOT_ALLOWED_VIEWING = 'KHONGCHOXEMHANG';

    /** Khách hàng được xem hàng nhưng KHông THỬ hàng. */
    public const ALLOWED_VIEWING_NO_TRIAL = 'CHOXEMHANGKHONGTHU';

    /** Khách hàng được thử hàng (hỗ trợ hoàn trả). */
    public const ALLOWED_TESTING = 'CHOTHUHANG';

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::NOT_ALLOWED_VIEWING, 'label' => __('Not allow viewing (default)')],
            ['value' => self::ALLOWED_VIEWING_NO_TRIAL, 'label' => __('Allow viewing, no trial')],
            ['value' => self::ALLOWED_TESTING, 'label' => __('Allow trial (refund supported)')],
        ];
    }
}
