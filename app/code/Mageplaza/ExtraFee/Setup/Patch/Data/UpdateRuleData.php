<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */
declare(strict_types=1);

namespace Mageplaza\ExtraFee\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Mageplaza\ExtraFee\Model\ResourceModel\Rule\CollectionFactory as RuleCollection;

/**
 * Patch is mechanism, that allows to do atomic upgrade data changes
 */
class UpdateRuleData implements
    DataPatchInterface,
    PatchRevertableInterface
{
    /**
     * @var ModuleDataSetupInterface $moduleDataSetup
     */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */

    /**
     * @var RuleCollection
     */
    protected $ruleCollection;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        RuleCollection $ruleCollection
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->ruleCollection  = $ruleCollection;
    }

    /**
     * Do Upgrade
     *
     * @return void
     */
    public function apply()
    {
        $setup = $this->moduleDataSetup;
        $setup->startSetup();
        $updateData     = [];
        $ruleCollection = $this->ruleCollection->create();

        foreach ($ruleCollection as $rule) {
            $ruleData              = $rule->getData();
            $ruleData['from_date'] = $ruleData['created_at'];
            $updateData[]          = $ruleData;
        }

        if (!empty($updateData)) {
            $setup->getConnection()->insertOnDuplicate(
                $setup->getTable('mageplaza_extrafee_rule'),
                $updateData
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function revert()
    {
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }
}
