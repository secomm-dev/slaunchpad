<?php

namespace Secomm\AddressDropdown\Block\Address\Field;

use Magento\Framework\View\Element\Template;

class SubCity extends Template
{
    /**
     * @var string
     */
    protected $_template = 'address/edit/field/subcity.phtml';

    /**
     * @var \Magento\Customer\Api\Data\AddressInterface
     */
    protected $_address;

    /**
     * @return string
     */
    public function getSubCityValue()
    {

        /** @var \Magento\Customer\Model\Data\Address $address */
        $address = $this->getAddress();
        $subCity = $address->getCustomAttribute('sub_city');
        if (!$subCity instanceof \Magento\Framework\Api\AttributeInterface) {
            return '';
        }

        return $subCity->getValue();
    }

    /**
     * Return the associated address.
     *
     * @return \Magento\Customer\Api\Data\AddressInterface
     */
    public function getAddress()
    {
        return $this->_address;
    }

    /**
     * Set the associated address.
     *
     * @param \Magento\Customer\Api\Data\AddressInterface $address
     */
    public function setAddress($address)
    {
        $this->_address = $address;
    }
}
