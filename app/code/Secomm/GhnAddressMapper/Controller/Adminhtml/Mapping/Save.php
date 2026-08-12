<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Secomm\GhnAddressMapper\Api\LocationMappingRepositoryInterface;
use Secomm\GhnAddressMapper\Api\Data\LocationMappingInterfaceFactory;
use Psr\Log\LoggerInterface;

class Save extends Action
{
    const ADMIN_RESOURCE = 'Secomm_GhnAddressMapper::mapping';

    public function __construct(
        Context $context,
        protected LocationMappingRepositoryInterface $repository,
        protected LocationMappingInterfaceFactory $mappingFactory,
        protected LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        // TEMP DIAGNOSTIC — remove after verifying POST structure
        $this->logger->info('GHN Address Mapper: Save POST debug', [
            'top_level_keys' => array_keys((array)$data),
            'has_data_key' => isset($data['data']),
            'data_keys' => isset($data['data']) ? array_keys((array)$data['data']) : null,
            'country_id_direct' => $data['country_id'] ?? '__MISSING__',
            'country_id_nested' => $data['data']['country_id'] ?? '__MISSING__',
            'region_id_direct' => $data['region_id'] ?? '__MISSING__',
            'region_id_nested' => $data['data']['region_id'] ?? '__MISSING__',
        ]);

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $id = $this->getRequest()->getParam('entity_id');

        try {
            if ($id) {
                $mapping = $this->repository->getById((int)$id);
            } else {
                $mapping = $this->mappingFactory->create();
            }

            $mapping->setCountryId($data['country_id'] ?? 'VN');
            $mapping->setRegionId((int)($data['region_id'] ?? 0));
            $mapping->setCityId((int)($data['city_id'] ?? 0));

            $mapping->setGhnProvinceId((int)($data['ghn_province_id'] ?? 0));
            $mapping->setGhnDistrictId((int)($data['ghn_district_id'] ?? 0));
            $mapping->setGhnWardCode((string)($data['ghn_ward_code'] ?? ''));

            $mapping->setStatus((int)($data['status'] ?? 1));
            $mapping->setPriority((int)($data['priority'] ?? 0));

            // Clear human-readable names so repository re-enriches from reference data
            $mapping->setRegionName(null);
            $mapping->setCityName(null);
            $mapping->setGhnProvinceName(null);
            $mapping->setGhnDistrictName(null);
            $mapping->setGhnWardName(null);

            $this->repository->save($mapping);
            $this->messageManager->addSuccessMessage(__('Mapping saved successfully.'));

            return $resultRedirect->setPath('ghn_address_mapper/mapping/index');
        } catch (NoSuchEntityException $e) {
            $this->logger->error('GHN Address Mapper: Save — entity not found', ['id' => $id, 'exception' => $e]);
            $this->messageManager->addErrorMessage(__('Could not find the mapping.'));
            return $resultRedirect->setPath('*/*/');
        } catch (CouldNotSaveException $e) {
            $this->logger->error('GHN Address Mapper: Save — could not save', ['id' => $id, 'exception' => $e]);
            $this->messageManager->addErrorMessage($e->getLogMessage());
            return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
        } catch (\Exception $e) {
            $this->logger->error('GHN Address Mapper: Save — unexpected error', ['id' => $id, 'exception' => $e]);
            $this->messageManager->addErrorMessage(__('Something went wrong while saving the mapping.'));
            return $resultRedirect->setPath('*/*/edit', ['entity_id' => $id]);
        }
    }
}
