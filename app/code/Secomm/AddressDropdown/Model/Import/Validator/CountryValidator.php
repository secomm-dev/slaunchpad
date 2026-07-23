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
use Secomm\AddressDropdown\Model\Import\AddressDropdown;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Secomm\AddressDropdown\Helper\Data;

/**
 * Extension point for row validation
 */
class CountryValidator implements ValidatorInterface
{
    /**
     * @var ValidationResultFactory
     */
    private $validationResultFactory;

    /**
     * @var CountryCollectionFactory
     */
    private CountryCollectionFactory $countryCollectionFactory;

    /**
     * @var Data
     */
    private Data $data;

    /**
     * @param ValidationResultFactory $validationResultFactory
     * @param CountryCollectionFactory $countryCollectionFactory
     */
    public function __construct(
        ValidationResultFactory $validationResultFactory,
        CountryCollectionFactory $countryCollectionFactory,
        Data                     $data
    )
    {
        $this->validationResultFactory = $validationResultFactory;
        $this->countryCollectionFactory = $countryCollectionFactory;
        $this->data = $data;
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

        if (!isset($rowData[AddressDropdown::ENTITY_ID_COLUMN])) {
            $errors[] = __('Missing required column "%column"', ['column' => AddressDropdown::ENTITY_ID_COLUMN]);
        }

        if (empty($rowData[AddressDropdown::ENTITY_ID_COLUMN])) {
            $errors[] = __('Missing required value of column "%column"', ['column' => AddressDropdown::ENTITY_ID_COLUMN]);
        }

        if (!empty($rowData[AddressDropdown::ENTITY_ID_COLUMN]) && !$this->data->hasCountryId($rowData[AddressDropdown::ENTITY_ID_COLUMN])) {
            $errors[] = __(
                'The "%1" country is incorrect. Verify the country and try again.',
                $rowData[AddressDropdown::ENTITY_ID_COLUMN]
            );
        }

        return $this->validationResultFactory->create(['errors' => $errors]);
    }
}
