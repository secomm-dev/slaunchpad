<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PancakeBridge\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentStatusMap\CollectionFactory;
use Secomm\PancakeFunction\Model\Mapping\PancakeStatusCatalog;
use Secomm\PancakeFunction\Model\ServiceCode;

/**
 * Ensure magento_order_status is filled on existing Pancake status map rows.
 */
class BackfillPancakeStatusMapMagentoStatus implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly PancakeStatusCatalog $catalog,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $defaults = [];
        foreach ($this->catalog->getDefaultMaps() as $row) {
            $defaults[$row['code']] = $row['magento_status'];
        }

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('service_code', ServiceCode::CODE);

        foreach ($collection as $map) {
            if ($map->getMagentoOrderStatus() !== '') {
                continue;
            }
            $code = $map->getExternalStatusCode();
            if (!isset($defaults[$code])) {
                continue;
            }
            $map->setMagentoOrderStatus($defaults[$code]);
            $map->getResource()->save($map);
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [SeedPancakeStatusMaps::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
