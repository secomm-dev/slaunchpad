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

use Adyen\Model\BalancePlatform\SameAmountRestriction;
use Magento\Framework\App\State;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Mageplaza\ExtraFee\Model\ResourceModel\Rule;
use Mageplaza\ExtraFee\Model\ResourceModel\Rule\CollectionFactory as RuleCollection;
use Psr\Log\LoggerInterface;

/**
 * Patch is mechanism, that allows to do atomic upgrade data changes
 */
class UpdateCombineRule implements
    DataPatchInterface,
    PatchRevertableInterface
{

    /**
     * @var State
     */
    protected $state;
    /**
     * @var ModuleDataSetupInterface $moduleDataSetup
     */
    private $moduleDataSetup;

    /**
     * @var RuleCollection
     */
    protected $ruleCollection;

    /**
     * Rule model factory
     *
     * @var Rule
     */
    protected $ruleResource;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param State
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param Rule $rule
     * @param RuleCollection $ruleCollection
     */
    public function __construct(
        State $state,
        ModuleDataSetupInterface $moduleDataSetup,
        Rule $rule,
        RuleCollection $ruleCollection,
        LoggerInterface $logger
    ) {
        $this->state = $state;
        $this->moduleDataSetup = $moduleDataSetup;
        $this->ruleResource    = $rule;
        $this->ruleCollection  = $ruleCollection;
        $this->logger          = $logger;
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

        try {
            $this->state->setAreaCode('adminhtml');
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // area code already set → ignore
        }
        $ruleCollection = $this->ruleCollection->create();

        foreach ($ruleCollection as $item) {
            try {
                $this->ruleResource->save($item);
            } catch (\Exception $e) {
                $this->logger->error(__("Can not save rule '%s'. Message: '%s", $item->getId(), $e->getMessage()));
            }
        }

        $setup->endSetup();
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
