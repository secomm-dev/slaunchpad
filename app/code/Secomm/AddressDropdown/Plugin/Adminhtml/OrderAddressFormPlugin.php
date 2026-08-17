<?php
declare(strict_types=1);

namespace Secomm\AddressDropdown\Plugin\Adminhtml;

use Magento\Directory\Model\RegionFactory;
use Magento\Sales\Block\Adminhtml\Order\Create\Form\Address as Subject;

class OrderAddressFormPlugin
{
    public function __construct(
        private readonly RegionFactory $regionFactory
    ) {
    }

    /**
     * Hydrate `region_id` from the stored region text when the order address
     * was saved without an explicit state/province id.
     *
     * @param Subject $subject
     * @param array $result
     * @return array
     */
    public function afterGetFormValues(Subject $subject, array $result): array
    {
        if (!empty($result['region_id']) || empty($result['country_id']) || empty($result['region'])) {
            return $result;
        }

        $region = $this->regionFactory->create();
        $regionValue = trim((string) $result['region']);
        $countryId = (string) $result['country_id'];

        if (ctype_digit($regionValue)) {
            $region->load((int) $regionValue);
        } else {
            $region->loadByCode(strtoupper($regionValue), $countryId);
        }

        if (!$region->getId()) {
            $region->loadByName($regionValue, $countryId);
        }

        if ($region->getId()) {
            $result['region_id'] = (int) $region->getId();
        }

        return $result;
    }

}
