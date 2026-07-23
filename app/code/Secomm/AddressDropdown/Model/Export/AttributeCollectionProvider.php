<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Export;

use Magento\Eav\Model\Entity\AttributeFactory;
use Magento\Framework\Data\Collection;
use Magento\ImportExport\Model\Export\Factory as CollectionFactory;
use Magento\InventoryApi\Api\Data\SourceItemInterface;

/**
 * @api
 */
class AttributeCollectionProvider
{
    const COUNTRY_FRONTEND_LABEL = "Country";
    const REGION_FRONTEND_LABEL = "State/Province";
    /**
     * @var Collection
     */
    private $collection;

    /**
     * @var AttributeFactory
     */
    private $attributeFactory;

    /**
     * @param CollectionFactory $collectionFactory
     * @param AttributeFactory $attributeFactory
     * @throws \InvalidArgumentException
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        AttributeFactory $attributeFactory
    ) {
        $this->collection = $collectionFactory->create(Collection::class);
        $this->attributeFactory = $attributeFactory;
    }

    /**
     * @return Collection
     * @throws \Exception
     */
    public function get(): Collection
    {
        if (count($this->collection) === 0) {
            /** @var \Magento\Eav\Model\Entity\Attribute $sourceCodeAttribute */
            $sourceCodeAttribute = $this->attributeFactory->create();
            $sourceCodeAttribute->setId(AddressDropdown::COLUMN_COUNTRY_ID);
            $sourceCodeAttribute->setDefaultFrontendLabel(self::COUNTRY_FRONTEND_LABEL);
            $sourceCodeAttribute->setAttributeCode(AddressDropdown::COLUMN_COUNTRY_ID);
            $sourceCodeAttribute->setBackendType('static');
            $sourceCodeAttribute->setSourceModel("Magento\Customer\Model\ResourceModel\Address\Attribute\Source\Country");
            $this->collection->addItem($sourceCodeAttribute);

            /** @var \Magento\Eav\Model\Entity\Attribute $sourceCodeAttribute */
            $sourceCodeAttribute = $this->attributeFactory->create();
            $sourceCodeAttribute->setId(AddressDropdown::REGION_ID);
            $sourceCodeAttribute->setDefaultFrontendLabel(self::REGION_FRONTEND_LABEL);
            $sourceCodeAttribute->setAttributeCode(AddressDropdown::REGION_ID);
            $sourceCodeAttribute->setBackendType('static');
            $sourceCodeAttribute->setSourceModel("Magento\Customer\Model\ResourceModel\Address\Attribute\Source\Region");
            $this->collection->addItem($sourceCodeAttribute);
        }

        return $this->collection;
    }
}
