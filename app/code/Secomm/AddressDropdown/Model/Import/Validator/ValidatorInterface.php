<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\AddressDropdown\Model\Import\Validator;

use Magento\Framework\Validation\ValidationResult;

/**
 * Extension point for row validation (Service Provider Interface - SPI)
 *
 * @api
 */
interface ValidatorInterface
{
    /**
     * @param array $rowData
     * @param int $rowNumber
     * @return ValidationResult
     */
    public function validate(array $rowData, int $rowNumber): ValidationResult;
}
