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
use Secomm\FulfillmentCore\Api\StatusMapRepositoryInterface;
use Secomm\FulfillmentCore\Model\FulfillmentStatusMapFactory;
use Secomm\FulfillmentCore\Model\ResourceModel\FulfillmentStatusMap\CollectionFactory;
use Secomm\Pancake\Model\Mapping\PancakeStatusCatalog;
use Secomm\Pancake\Model\ServiceCode;

/**
 * Seed default Magento status ↔ Pancake status maps (idempotent; same idea as empty warehouse grid + defaults).
 */
class SeedPancakeStatusMaps implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly PancakeStatusCatalog $catalog,
        private readonly CollectionFactory $collectionFactory,
        private readonly FulfillmentStatusMapFactory $mapFactory,
        private readonly StatusMapRepositoryInterface $repository
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        foreach ($this->catalog->getDefaultMaps() as $row) {
            $existing = $this->collectionFactory->create();
            $existing->addFieldToFilter('service_code', ServiceCode::CODE);
            $existing->addFieldToFilter('external_status_code', $row['code']);
            $existing->setPageSize(1);
            if ($existing->getFirstItem()->getEntityId()) {
                continue;
            }

            $map = $this->mapFactory->create();
            $map->setServiceCode(ServiceCode::CODE)
                ->setExternalStatusCode($row['code'])
                ->setExternalStatusLabel($row['label'])
                ->setMagentoOrderStatus($row['magento_status'])
                ->setIsActive(true);
            $this->repository->save($map);
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
