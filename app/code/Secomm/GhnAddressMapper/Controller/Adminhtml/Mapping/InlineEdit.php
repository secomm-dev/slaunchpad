<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Secomm\GhnAddressMapper\Api\LocationMappingRepositoryInterface;

class InlineEdit extends Action
{
    const ADMIN_RESOURCE = 'Secomm_GhnAddressMapper::mapping';

    public function __construct(
        Context $context,
        protected JsonFactory $resultJsonFactory,
        protected LocationMappingRepositoryInterface $repository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        $error = false;
        $messages = [];

        $postItems = $this->getRequest()->getParam('items', []);
        if (!is_array($postItems) || empty($postItems)) {
            $messages[] = __('Please correct the data sent.');
            $error = true;
        }

        foreach ($postItems as $entityId => $data) {
            try {
                $mapping = $this->repository->getById((int)$entityId);

                if (isset($data['status'])) {
                    $mapping->setStatus((int)$data['status']);
                }
                if (isset($data['priority'])) {
                    $mapping->setPriority((int)$data['priority']);
                }
                if (isset($data['ghn_province_id'])) {
                    $mapping->setGhnProvinceId((int)$data['ghn_province_id']);
                }
                if (isset($data['ghn_district_id'])) {
                    $mapping->setGhnDistrictId((int)$data['ghn_district_id']);
                }
                if (isset($data['ghn_ward_code'])) {
                    $mapping->setGhnWardCode($data['ghn_ward_code']);
                }

                // Clear denormalized names so repository re-enriches from reference data
                $mapping->setRegionName(null);
                $mapping->setCityName(null);
                $mapping->setGhnProvinceName(null);
                $mapping->setGhnDistrictName(null);
                $mapping->setGhnWardName(null);

                $this->repository->save($mapping);
            } catch (\Exception $e) {
                $messages[] = __('[ID %1] Could not save: %2', $entityId, $e->getMessage());
                $error = true;
            }
        }

        return $resultJson->setData([
            'messages' => $messages,
            'error' => $error
        ]);
    }
}
