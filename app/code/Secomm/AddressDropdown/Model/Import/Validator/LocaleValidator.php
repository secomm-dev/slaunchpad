<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\AddressDropdown\Model\Import\Validator;

use Magento\Framework\Locale\Config;
use Magento\Framework\Validation\ValidationResult;
use Magento\Framework\Validation\ValidationResultFactory;
use Secomm\AddressDropdown\Helper\Data as HelperData;
use Secomm\AddressDropdown\Model\Import\AddressDropdown;

/**
 * Extension point for row validation
 */
class LocaleValidator implements ValidatorInterface
{
    /**
     * @var ValidationResultFactory
     */
    private $validationResultFactory;

    /**
     * @var HelperData
     */
    private HelperData $helperData;

    /**
     * @param ValidationResultFactory $validationResultFactory
     * @param Config $localeConfig
     */
    public function __construct(
        ValidationResultFactory $validationResultFactory,
        HelperData              $helperData
    )
    {
        $this->validationResultFactory = $validationResultFactory;
        $this->helperData = $helperData;
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
        if (!$this->helperData->hasLocale($rowData[AddressDropdown::LOCALE])) {
            $errors[] = __(
                'The "%1" locale is incorrect. Verify the locale and try again.',
                $rowData[AddressDropdown::LOCALE]
            );
        }

        return $this->validationResultFactory->create(['errors' => $errors]);
    }
}
