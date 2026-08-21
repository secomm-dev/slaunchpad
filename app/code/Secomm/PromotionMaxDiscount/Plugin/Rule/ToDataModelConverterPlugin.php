<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PromotionMaxDiscount\Plugin\Rule;

use Magento\SalesRule\Api\Data\RuleExtensionFactory;
use Magento\SalesRule\Model\Converter\ToDataModel;
use Magento\SalesRule\Model\Data\Rule as RuleDataModel;
use Magento\SalesRule\Model\Rule;

/**
 * Read path for the salesrule.maximum_discount_amount extension attribute (SL-021 /
 * SPEC-FEAT-008 §6). Every RuleRepository read — getById, getList items, and the data
 * model returned by save() — converts the persisted Model\Rule through
 * ToDataModel::toDataModel(). The resulting data model keeps the raw column only in
 * its protected storage (no public getData), while service/REST consumers see declared
 * extension attributes: copy the column onto the extension object here, one hook for
 * all read paths.
 */
final class ToDataModelConverterPlugin
{
    /**
     * @var RuleExtensionFactory
     */
    private RuleExtensionFactory $ruleExtensionFactory;

    /**
     * @param RuleExtensionFactory $ruleExtensionFactory
     */
    public function __construct(RuleExtensionFactory $ruleExtensionFactory)
    {
        $this->ruleExtensionFactory = $ruleExtensionFactory;
    }

    /**
     * DECIMAL columns come back from the DB as strings; the extension attribute is
     * typed float. A NULL column (native "no cap") keeps the attribute absent.
     *
     * @param ToDataModel $subject
     * @param RuleDataModel $result
     * @param Rule $ruleModel
     * @return RuleDataModel
     */
    public function afterToDataModel(ToDataModel $subject, RuleDataModel $result, Rule $ruleModel): RuleDataModel
    {
        $value = $ruleModel->getData('maximum_discount_amount');
        if ($value !== null) {
            $extension = $result->getExtensionAttributes();
            if ($extension === null) {
                $extension = $this->ruleExtensionFactory->create();
                $result->setExtensionAttributes($extension);
            }
            $extension->setMaximumDiscountAmount((float) $value);
        }
        return $result;
    }
}
