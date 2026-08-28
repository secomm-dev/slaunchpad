<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Pancake\Controller\Adminhtml\WarehouseMap;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Secomm\FulfillmentCore\Api\WarehouseMapRepositoryInterface;
use Secomm\Pancake\Model\Order\PancakeOrderExporter;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_Pancake::warehouse_map';

    public function __construct(
        Context $context,
        private readonly WarehouseMapRepositoryInterface $repository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('entity_id');
        if ($id <= 0) {
            return $resultRedirect->setPath('*/*/index');
        }

        try {
            $map = $this->repository->getById($id);
            if ($map->getServiceCode() !== PancakeOrderExporter::SERVICE_CODE) {
                $this->messageManager->addErrorMessage(__('Cannot delete a map owned by another service.'));
                return $resultRedirect->setPath('*/*/index');
            }
            $this->repository->delete($map);
            $this->messageManager->addSuccessMessage(__('Warehouse map deleted.'));
        } catch (\Throwable) {
            $this->messageManager->addErrorMessage(__('Unable to delete warehouse map.'));
        }

        return $resultRedirect->setPath('*/*/index');
    }
}
