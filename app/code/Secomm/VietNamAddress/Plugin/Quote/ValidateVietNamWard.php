<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 *
 * TASK-9EX975 Slice A (AC-003): server-side validation of the VN ward(province)
 * relationship for the ADMIN ORDER CREATE/EDIT flow — the quote save that becomes the
 * order's billing/shipping addresses. When country = VN, the submitted ward (Magento
 * `city`) MUST belong to the submitted province (Magento `region_id`). An invalid ward
 * is neutralised (cleared on the quote address) so a malformed ward is never persisted
 * into the order.
 *
 * Mirrors Plugin/Customer/Address/ValidateVietNamWard (customer-address precedent):
 * gate country = VN, reuse the GENERIC Secomm_AddressDropdown collection (no data
 * duplication — DEC-019), and never break the save: a validation fault is logged and
 * skipped (non-fatal).
 *
 * Adminhtml-scoped (etc/adminhtml/di.xml) — storefront checkout is NOT touched here.
 */

declare(strict_types=1);

namespace Secomm\VietNamAddress\Plugin\Quote;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
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
     * Validate ward(province) relationship for VN before the quote (order draft) is saved.
     *
     * @return array [CartInterface] (invalid wards neutralised on both addresses)
     */
    public function beforeSave(CartRepositoryInterface $subject, CartInterface $cart): array
    {
        try {
            if (!$cart instanceof Quote) {
                return [$cart];
            }

            $this->validateAddress($cart->getBillingAddress());
            $this->validateAddress($cart->getShippingAddress());
        } catch (\Exception $e) {
            // Intentional non-fatal guard: a validation fault must never block the save.
            // Logged, not swallowed silently.
            $this->logger->error(
                'Secomm_VietNamAddress: VN ward validation failed on quote save; skipping validation.',
                ['exception' => (string) $e]
            );
        }

        return [$cart];
    }

    /**
     * Neutralise an invalid ward on one quote address (billing or shipping).
     */
    private function validateAddress(?Address $address): void
    {
        if ($address === null || $address->getCountryId() !== 'VN') {
            return;
        }

        $regionId = (string) ($address->getRegionId() ?? '');
        $ward = (string) ($address->getCity() ?? '');

        // Incomplete VN address: nothing to validate server-side; let Magento handle it.
        if ($regionId === '' || $ward === '') {
            return;
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
            // a false ward into the order. Logged for visibility.
            $this->logger->warning(
                'Secomm_VietNamAddress: invalid VN ward for province; neutralising quote address city.',
                ['region_id' => $regionId, 'ward' => $ward]
            );
            $address->setCity('');
        }
    }
}
