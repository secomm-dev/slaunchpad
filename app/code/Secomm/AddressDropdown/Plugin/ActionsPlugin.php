<?php

namespace Secomm\AddressDropdown\Plugin;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Ui\Component\Listing\Address\Column\Actions;

class ActionsPlugin
{
    /**
     * @var AddressRepositoryInterface
     */
    private AddressRepositoryInterface $addressRepository;

    public function __construct(
        AddressRepositoryInterface $addressRepository,
    )
    {
        $this->addressRepository = $addressRepository;
    }

    /**
     * @param Actions $subject
     * @param array $result
     * @param array $dataSource
     * @return array
     */
    public function afterPrepareDataSource(Actions $subject, array $result, array $dataSource): array
    {
        if (isset($result['data']['items']) && count($result['data']['items']) > 0) {
            foreach ($result['data']['items'] as &$item) {
                if (!isset($item['sub_city'])) {
                    $addressId = $item['entity_id'];
                    $customerId = $item['parent_id'];
                    $address = $this->addressRepository->getById($addressId)->setCustomerId($customerId);
                    $item['sub_city'] = $address->getExtensionAttributes()->getSubCity();
                }
            }
        }

        return $result;
    }
}
