<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PancakeBridge\Controller\Adminhtml\WarehouseMap;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Secomm\FulfillmentCore\Api\WarehouseMapRepositoryInterface;
use Secomm\FulfillmentCore\Model\FulfillmentWarehouseMapFactory;
use Secomm\FulfillmentCore\Model\Warehouse\WarehouseMapConflictException;
use Secomm\PancakeBridge\Model\Order\PancakeOrderExporter;
use Secomm\PancakeFunction\Model\Warehouse\PosWarehouseCatalog;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_PancakeBridge::warehouse_map';

    public function __construct(
        Context $context,
        private readonly WarehouseMapRepositoryInterface $repository,
        private readonly FulfillmentWarehouseMapFactory $mapFactory,
        private readonly PosWarehouseCatalog $warehouseCatalog
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $data = (array) $this->getRequest()->getPostValue();
        if (isset($data['data']) && is_array($data['data'])) {
            $data = array_merge($data, $data['data']);
        }
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $entityId = isset($data['entity_id']) ? (int) $data['entity_id'] : 0;
            $map = $entityId > 0 ? $this->repository->getById($entityId) : $this->mapFactory->create();

            // Hard-scope to pancake — never allow other service_code from this UI.
            $map->setServiceCode(PancakeOrderExporter::SERVICE_CODE)
                ->setMagentoSourceCode(trim((string) ($data['magento_source_code'] ?? '')))
                ->setExternalWarehouseId(trim((string) ($data['external_warehouse_id'] ?? '')))
                ->setIsActive(!empty($data['is_active']));

            if ($map->getMagentoSourceCode() === '' || $map->getExternalWarehouseId() === '') {
                throw new LocalizedException(__('Magento source and Pancake warehouse are required.'));
            }

            $label = $this->warehouseCatalog->getOptions()[$map->getExternalWarehouseId()] ?? null;
            $map->setExternalWarehouseLabel($label);

            $this->repository->save($map);
            $this->messageManager->addSuccessMessage(__('Warehouse map saved.'));
            return $resultRedirect->setPath('*/*/index');
        } catch (WarehouseMapConflictException $e) {
            $this->messageManager->addErrorMessage(__($e->getMessage()));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Unable to save warehouse map.'));
        }

        return $resultRedirect->setPath('*/*/edit', ['entity_id' => $data['entity_id'] ?? null]);
    }
}
