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
 * SPEC-FEAT-FQWEQ3 §36 — preserve legacy business behavior (payment_type_id values as used by
 * the legacy Secomm_GiaoHangNhanh integration: 1 = shop pays, 2 = consignee pays).
 */
class PaymentType implements OptionSourceInterface
{
    public const SHOP_PAYS = 1;
    public const CONSIGNEE_PAYS = 2;

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::SHOP_PAYS, 'label' => __('Shop pays the shipping fee')],
            ['value' => self::CONSIGNEE_PAYS, 'label' => __('Consignee pays the shipping fee')],
        ];
    }
}
