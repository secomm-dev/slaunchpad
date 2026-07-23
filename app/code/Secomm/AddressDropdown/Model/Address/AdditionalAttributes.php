<?php

namespace Secomm\AddressDropdown\Model\Address;

use Magento\Framework\Api\AbstractSimpleObject;

class AdditionalAttributes extends AbstractSimpleObject implements \Magento\Customer\Api\Data\AddressExtensionInterface
{
    /**
     * @param string $note
     * @return void
     */
    public function setSubCity($subCity)
    {
        $this->setData('sub_city', $subCity);
    }

    /**
     * @return mixed|null
     */
    public function getSubCity()
    {
        return $this->_get('sub_city');
    }
}
