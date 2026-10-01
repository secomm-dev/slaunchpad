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
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */
declare(strict_types=1);

namespace Mageplaza\RMA\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Mageplaza\RMA\Model\Config\Source\RMAStatus\Action;
use Mageplaza\RMA\Model\StatusFactory;

/**
 * Class UpdateData
 * @package Mageplaza\RMA\Setup\Patch\Data
 */
class UpdateData implements
    DataPatchInterface,
    PatchRevertableInterface
{
    /**
     * @var ModuleDataSetupInterface $moduleDataSetup
     */
    private $moduleDataSetup;

    /**
     * @var DateTime
     */
    protected $_dateTime;

    /**
     * @var StatusFactory
     */
    protected $statusFactory;

    /**
     * UpdateData constructor.
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param DateTime $dateTime
     * @param StatusFactory $statusFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        DateTime $dateTime,
        StatusFactory $statusFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->_dateTime       = $dateTime;
        $this->statusFactory   = $statusFactory;
    }

    /**
     * Do Upgrade
     *
     * @return void
     */
    public function apply()
    {
        $setup     = $this->moduleDataSetup;
        $installer = $setup;
        $installer->startSetup();

        /** Add default RMA status */
        $data = [
            [
                'name'           => __('Pending'),
                'label'          => __('Pending'),
                'comment'        => '',
                'enable_comment' => 0,
                'is_active'      => 1,
                'description'    => '',
                'allow_action'   => 0,
                'updated_at'     => $this->_dateTime->date(),
                'created_at'     => $this->_dateTime->date()
            ],
            [
                'name'           => __('Processing'),
                'label'          => __('Processing'),
                'comment'        => '',
                'enable_comment' => 0,
                'is_active'      => 1,
                'description'    => '',
                'allow_action'   => Action::SHIPPING_LABEL,
                'updated_at'     => $this->_dateTime->date(),
                'created_at'     => $this->_dateTime->date()
            ],
            [
                'name'           => __('Rejected'),
                'label'          => __('Rejected'),
                'comment'        => '',
                'enable_comment' => 0,
                'is_active'      => 1,
                'description'    => '',
                'allow_action'   => 0,
                'updated_at'     => $this->_dateTime->date(),
                'created_at'     => $this->_dateTime->date()
            ],
            [
                'name'           => __('Completed'),
                'label'          => __('Completed'),
                'comment'        => '',
                'enable_comment' => 0,
                'is_active'      => 1,
                'description'    => '',
                'allow_action'   => Action::SHIPPING_LABEL . ',' . Action::CREDIT_MEMO . ',' . Action::REORDER,
                'updated_at'     => $this->_dateTime->date(),
                'created_at'     => $this->_dateTime->date()
            ],
            [
                'name'           => __('Canceled'),
                'label'          => __('Canceled'),
                'comment'        => '',
                'enable_comment' => 0,
                'is_active'      => 1,
                'description'    => '',
                'allow_action'   => 0,
                'updated_at'     => $this->_dateTime->date(),
                'created_at'     => $this->_dateTime->date()
            ]
        ];

        foreach ($data as $item) {
            $status = $this->statusFactory->create()->load($item['name'], 'name');
            if ($status->getId()) {
                $setup->getConnection()->delete($setup->getTable('mageplaza_rma_status'),
                    ['status_id = ?' => $status->getId()]);
            }
        }

        $setup->getConnection()->insertMultiple($setup->getTable('mageplaza_rma_status'), $data);

        $installer->endSetup();
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
