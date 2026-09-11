<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\PancakeBridge\Controller\Adminhtml\StatusMap;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Secomm\FulfillmentCore\Api\StatusMapRepositoryInterface;
use Secomm\FulfillmentCore\Model\FulfillmentStatusMapFactory;
use Secomm\FulfillmentCore\Model\Status\StatusMapConflictException;
use Secomm\Pancake\Model\Mapping\PancakeStatusCatalog;
use Secomm\PancakeBridge\Model\Order\PancakeOrderExporter;

/**
 * Save Magento order status ↔ Pancake status map (hard-scoped to service=pancake).
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_PancakeBridge::status_map';

    public function __construct(
        Context $context,
        private readonly StatusMapRepositoryInterface $repository,
        private readonly FulfillmentStatusMapFactory $mapFactory,
        private readonly PancakeStatusCatalog $statusCatalog
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

            $map->setServiceCode(PancakeOrderExporter::SERVICE_CODE)
                ->setMagentoOrderStatus(trim((string) ($data['magento_order_status'] ?? '')))
                ->setExternalStatusCode(trim((string) ($data['external_status_code'] ?? '')))
                ->setIsActive(!empty($data['is_active']));

            if ($map->getMagentoOrderStatus() === '' || $map->getExternalStatusCode() === '') {
                throw new LocalizedException(__('Magento order status and Pancake status are required.'));
            }

            $label = $this->statusCatalog->getOptions()[$map->getExternalStatusCode()] ?? null;
            $map->setExternalStatusLabel($label);

            $this->repository->save($map);
            $this->messageManager->addSuccessMessage(__('Status map saved.'));
            return $resultRedirect->setPath('*/*/index');
        } catch (StatusMapConflictException $e) {
            $this->messageManager->addErrorMessage(__($e->getMessage()));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Unable to save status map.'));
        }

        return $resultRedirect->setPath('*/*/edit', ['entity_id' => $data['entity_id'] ?? null]);
    }
}
