<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 *
 * TASK-9EX975 Slice A (AC-003): server-side validation of the VN ward(province)
 * relationship for the ADMIN ORDER ADDRESS EDIT form (Sales > Orders > edit address).
 * When country = VN, the submitted ward (Magento `city`) MUST belong to the submitted
 * province (Magento `region_id`). An invalid ward is neutralised (cleared) so a
 * malformed ward is never persisted on the order address.
 *
 * Mirrors Plugin/Customer/Address/ValidateVietNamWard (customer-address precedent):
 * gate country = VN, reuse the GENERIC Secomm_AddressDropdown collection (no data
 * duplication — DEC-019), and never break the save: a validation fault is logged and
 * skipped (non-fatal).
 *
 * Adminhtml-scoped (etc/adminhtml/di.xml).
 */

declare(strict_types=1);

namespace Secomm\VietNamAddress\Plugin\Sales;

use Magento\Sales\Api\OrderAddressRepositoryInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollectionFactory;

class ValidateVietNamWard
{
    public function __construct(
        private readonly CityLocaleCollectionFactory $cityCollectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Validate ward(province) relationship for VN before an order address is saved.
     *
     * @return array [OrderAddressInterface] (city neutralised if the ward is invalid)
     */
    public function beforeSave(
        OrderAddressRepositoryInterface $subject,
        OrderAddressInterface $address
    ): array {
        try {
            if ($address->getCountryId() !== 'VN') {
                return [$address];
            }

            $regionId = (string) ($address->getRegionId() ?? '');
            $ward = (string) ($address->getCity() ?? '');

            // Incomplete VN address: nothing to validate server-side; let Magento handle it.
            if ($regionId === '' || $ward === '') {
                return [$address];
            }

            $collection = $this->cityCollectionFactory->create();
            $collection->addFieldToFilter('region_id', $regionId);
            // TASK-ADT94K: the schema renderer submits the locale-resolved name (e.g. vi_VN
            // "Hoàn Kiếm"), the legacy renderer the ASCII default_name — match either.
            $connection = $collection->getConnection();
            $collection->getSelect()->where(
                $connection->quoteInto('main_table.default_name = ?', $ward)
                . ' OR ' . $connection->quoteInto('rname.name = ?', $ward)
            );
            $collection->setPageSize(1)->setCurPage(1);

            if ($collection->getSize() === 0) {
                // Ward does not belong to the province -> neutralise to avoid persisting
                // a false ward. Logged for visibility (do not silently guess).
                $this->logger->warning(
                    'Secomm_VietNamAddress: invalid VN ward for province; neutralising order address city.',
                    ['region_id' => $regionId, 'ward' => $ward]
                );
                $address->setCity('');
            }
        } catch (\Exception $e) {
            // Intentional non-fatal guard: a validation fault must never block the save.
            // Logged, not swallowed silently.
            $this->logger->error(
                'Secomm_VietNamAddress: VN ward validation failed on order address save; skipping validation.',
                ['exception' => (string) $e]
            );
        }

        return [$address];
    }
}
