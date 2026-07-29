<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 *
 * SL-003 / FEAT-005 (DEC-017): server-side validation of the VN 2-level relationship
 * for cart shipping estimation. When country = VN, the submitted ward (Magento `city`)
 * MUST belong to the submitted province (Magento `region_id`). An invalid ward is
 * neutralised (cleared on the RateRequest) so carriers never rate on a false ward.
 *
 * Reuse (DEC-019): the city/ward data + collection come from the GENERIC
 * Secomm_AddressDropdown (no data duplication). VN-specific logic only.
 *
 * Never breaks rate collection: a validation fault is logged and skipped (spec:
 * "Address API failure does not break the cart").
 */

declare(strict_types=1);

namespace Secomm\VietNamAddress\Plugin\Cart;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Shipping;
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
     * Validate ward(province) relationship for VN before carriers collect rates.
     *
     * @return array [RateRequest] (unchanged-ish; dest city neutralised if invalid)
     */
    public function beforeCollectRates(Shipping $subject, RateRequest $request): array
    {
        try {
            if ($request->getDestCountryId() !== 'VN') {
                return [$request];
            }

            $regionId = (string) ($request->getDestRegionId() ?? '');
            $ward = (string) ($request->getDestCity() ?? '');

            // Incomplete VN address: the frontend gates the rate request until both
            // province + ward are selected; nothing to validate server-side here.
            if ($regionId === '' || $ward === '') {
                return [$request];
            }

            $collection = $this->cityCollectionFactory->create();
            $collection->addFieldToFilter('region_id', $regionId);
            $collection->addFieldToFilter('default_name', $ward);
            $collection->setPageSize(1)->setCurPage(1);

            if ($collection->getSize() === 0) {
                // Ward does not belong to the province -> neutralise to avoid a
                // misleading rate. Logged for visibility (do not silently guess).
                $this->logger->warning(
                    'Secomm_VietNamAddress: invalid VN ward for province; neutralising dest city.',
                    ['region_id' => $regionId, 'ward' => $ward]
                );
                $request->setDestCity('');
            }
        } catch (\Exception $e) {
            // Intentional non-fatal guard (spec: cart must not break on API failure).
            // Logged, not swallowed silently.
            $this->logger->error(
                'Secomm_VietNamAddress: VN ward validation failed; skipping validation.',
                ['exception' => (string) $e]
            );
        }

        return [$request];
    }
}
