<?php

namespace Secomm\AddressDropdown\Plugin;

use Magento\Customer\Api\AddressRepositoryInterface as Subject;
use Magento\Customer\Api\Data\AddressInterface as Entity;
use Secomm\AddressDropdown\Helper\Address as AddressHelper;
use Secomm\AddressDropdown\Model\Constant;

/**
 * Class AddSubCityFieldToAddressEntity
 *
 * @package Yireo\ExampleAddressFieldNote\Plugin
 */
class AddSubCityFieldToAddressEntity
{
    protected $httpRequest;
    protected $logger;

    public function __construct(
        \Magento\Framework\App\RequestInterface $httpRequest,
        \Magento\Framework\Logger\Monolog $logger,
        protected AddressHelper $addressHelper
    )
    {
        $this->httpRequest = $httpRequest;
        $this->logger = $logger;
    }

    /**
     * @param Subject $subject
     * @param Entity $entity
     *
     * @return Entity
     */
    public function afterGetById(Subject $subject, Entity $entity)
    {
        $extensionAttributes = $entity->getExtensionAttributes();
        if ($extensionAttributes === null) {
            return $entity;
        }

        $subCity = $this->getSubCityName($entity);
        $extensionAttributes->setSubCity($subCity);
        $entity->setExtensionAttributes($extensionAttributes);

        return $entity;
    }

    /**
     * @param Subject $subject
     * @param Entity $entity
     *
     * @return [Entity]
     */
    public function beforeSave(Subject $subject, Entity $entity)
    {
        $extensionAttributes = $entity->getExtensionAttributes();
        if ($extensionAttributes === null) {
            return [$entity];
        }
        if ($entity->getCustomAttribute(Constant::SUBCITY_CODE)) {
            return [$entity];
        }

        $subCity = $this->httpRequest->getParam('sub_city');
        $entity->setCustomAttribute(Constant::SUBCITY_CODE, $subCity);
        return [$entity];
    }

    /**
     * @param Entity $entity
     *
     * @return string
     */
    private function getSubCityName(Entity $entity)
    {
        $attribute = $entity->getCustomAttribute('sub_city');
        $cityDefaultName = $entity->getCity();
        if ($attribute) {
            return $this->addressHelper->getSubCityNameByDefaultName($attribute->getValue(), $cityDefaultName);
        }

        return '';
    }
}
