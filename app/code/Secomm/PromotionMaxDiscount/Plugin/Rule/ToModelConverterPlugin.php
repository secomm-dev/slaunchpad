<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PromotionMaxDiscount\Plugin\Rule;

use Magento\SalesRule\Model\Converter\ToModel;
use Magento\SalesRule\Model\Data\Rule as RuleDataModel;
use Magento\SalesRule\Model\Rule;

/**
 * Write path for the salesrule.maximum_discount_amount extension attribute (SL-021 /
 * SPEC-FEAT-008 §6, decision D2). ToModel::toModel() builds the persistable model via
 * interface reflection (DataObjectProcessor::buildOutputDataArray), which nests
 * extension attributes under a non-column "extension_attributes" key — the cap value
 * would be silently dropped on save. Copy it onto the model flat, before the
 * repository persists the model the converter returns.
 *
 * A null/absent attribute is skipped on purpose: the converter's null-stripping merge
 * then keeps the stored column value, so a save that omits the field (e.g. a REST PUT
 * without it) never resets the cap. Clearing an existing cap goes through the admin
 * path (loadPost with null) or by saving 0 (both mean "unlimited").
 */
final class ToModelConverterPlugin
{
    /**
     * @param ToModel $subject
     * @param Rule $result
     * @param RuleDataModel $dataModel
     * @return Rule
     */
    public function afterToModel(ToModel $subject, Rule $result, RuleDataModel $dataModel): Rule
    {
        $extension = $dataModel->getExtensionAttributes();
        if ($extension !== null && $extension->getMaximumDiscountAmount() !== null) {
            $result->setData('maximum_discount_amount', $extension->getMaximumDiscountAmount());
        }
        return $result;
    }
}
