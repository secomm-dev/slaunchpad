<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\AddressDropdown\Model\Import\Validator;

use Magento\Framework\Validation\ValidationResult;
use Magento\Framework\Validation\ValidationResultFactory;
use Secomm\AddressDropdown\Api\Data\RegionInterface;
use Secomm\AddressDropdown\Model\Constant;
use Secomm\AddressDropdown\Model\Import\AddressDropdown;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel;
use Secomm\AddressDropdown\Model\RegionFactory;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel\Collection as RegionModelCollection;
use Secomm\AddressDropdown\Model\ResourceModel\RegionModel\CollectionFactory as RegionCollectionFactory;

/**
 * Extension point for row validation
 */
class RegionValidator implements ValidatorInterface
{
    /**
     * @var ValidationResultFactory
     */
    private $validationResultFactory;

    /**
     * @var RegionModel
     */
    protected RegionModel $regionModel;

    /**
     * @var RegionFactory
     */
    protected RegionFactory $regionFactory;

    /**
     * @var RegionCollectionFactory
     */
    private RegionCollectionFactory $collectionFactory;

    /**
     * @param ValidationResultFactory $validationResultFactory
     * @param RegionModel $regionModel
     * @param RegionFactory $regionFactory
     * @param RegionCollectionFactory $collectionFactory
     */
    public function __construct(
        ValidationResultFactory $validationResultFactory,
        RegionModel             $regionModel,
        RegionFactory           $regionFactory,
        RegionCollectionFactory $collectionFactory
    )
    {
        $this->validationResultFactory = $validationResultFactory;
        $this->regionModel = $regionModel;
        $this->regionFactory = $regionFactory;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Validate row data country_id
     *
     * @param array $rowData
     * @param int $rowNumber
     * @return ValidationResult
     */
    public function validate(array $rowData, int $rowNumber): ValidationResult
    {
        $errors = [];

        if (!isset($rowData[AddressDropdown::REGION_DEFAULT_NAME])) {
            $errors[] = __('Missing required column "%column"', ['column' => AddressDropdown::REGION_DEFAULT_NAME]);
        }

        if (empty($rowData[AddressDropdown::REGION_CODE])) {
            $errors[] = __('Missing required value of column "%column"', ['column' => AddressDropdown::REGION_CODE]);
        }

        if (!empty($rowData[AddressDropdown::REGION_CODE])
            && $this->isExistCode(
                $rowData[AddressDropdown::REGION_CODE],
                $rowData[RegionInterface::COUNTRY_ID],
                $rowData[AddressDropdown::REGION_DEFAULT_NAME])
        ) {
            $errors[] = __('Region code value of column "%column" is exists', ['column' => AddressDropdown::REGION_CODE]);
        }

        if (empty($rowData[AddressDropdown::REGION_DEFAULT_NAME])) {
            $errors[] = __('Missing required value of column "%column"', ['column' => AddressDropdown::REGION_DEFAULT_NAME]);
        }
        if (empty($rowData[AddressDropdown::REGION_NAME]) && $rowData[AddressDropdown::LOCALE] !== Constant::DEFAULT_LOCALE) {
            $errors[] = __('Missing required value of column "%column"', ['column' => AddressDropdown::REGION_NAME]);
        }

        return $this->validationResultFactory->create(['errors' => $errors]);
    }

    /**
     * @param $regionCode
     * @param $countryId
     * @param $regionDefaultName
     * @return bool
     */
    public function isExistCode($regionCode, $countryId, $regionDefaultName): bool
    {
        /** @var RegionModelCollection $modelCollectionFactory */
        $modelCollectionFactory = $this->collectionFactory->create();
        $modelCollectionFactory->addFieldToFilter(RegionInterface::CODE, $regionCode)
            ->addFieldToFilter(RegionInterface::COUNTRY_ID, $countryId);
        $regionData = $modelCollectionFactory->getFirstItem();

        if ($regionData->getRegionId() && $regionData->getDefaultName() !== $regionDefaultName) {
            return true;
        }
        return false;
    }
}
