<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Customer\Address\Config\Column;

use Secomm\AddressDropdown\Helper\Address;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;

class City extends Column
{
    /** @var Address  */
    protected $addressHelper;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        Address $addressHelper,
        array $components = [],
        array $data = []
    )   {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->addressHelper = $addressHelper;
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $address = $this->addressHelper->getAddressObjById($item['entity_id']);
                if ($address->getData('city')) {
                    $regionId = $address->getData('region_id') ?? null;
                    $cityName = $this->addressHelper->getCityNameByDefaultName(
                        $address->getData('city'),
                        $regionId
                    );
                    $item[$this->getData('name')] = $cityName;
                }

            }
        }
        return $dataSource;
    }
}

