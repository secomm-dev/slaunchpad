<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PromotionMaxDiscount\Plugin\Adminhtml\Rule;

use Magento\SalesRule\Model\Rule;

/**
 * Normalises the admin form's empty Maximum Discount Amount submit to NULL
 * (TASK-67GGPR AC-2).
 *
 * A visible-but-cleared input submits '' — an empty string into the nullable
 * DECIMAL column either errors (strict SQL mode) or degrades to 0, so it is
 * converted to NULL ("unlimited") before loadPost sets the data. A disabled
 * field (simple_action != by_percent) is not submitted at all: the key stays
 * absent and the stored cap is kept (AC-3) — this plugin must therefore NOT
 * touch absent keys.
 */
final class LoadPostNormalizerPlugin
{
    /**
     * @param Rule $subject
     * @param array $data
     * @return array
     */
    public function beforeLoadPost(Rule $subject, array $data): array
    {
        if (isset($data['maximum_discount_amount']) && $data['maximum_discount_amount'] === '') {
            $data['maximum_discount_amount'] = null;
        }
        return [$data];
    }
}
