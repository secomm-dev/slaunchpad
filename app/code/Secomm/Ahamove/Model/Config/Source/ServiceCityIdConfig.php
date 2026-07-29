<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
namespace Secomm\Ahamove\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Secomm\Ahamove\Model\ResourceModel\AhamoveCity\AhamoveCityCollectionFactory;

class ServiceCityIdConfig implements OptionSourceInterface
{
    public function __construct(
        protected AhamoveCityCollectionFactory $ahamoveCityCollectionFactory
    )
    {
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        $listCities = $this->ahamoveCityCollectionFactory->create()->getData();
        foreach ($listCities as $valueCity) {
            $options[] = [
                'value' => $valueCity['city_id'],
                'label' => $valueCity['name_vi_vn']
            ];
        }

        return $options;
    }
}
