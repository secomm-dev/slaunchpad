<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Plugin;

use Magento\Framework\Validation\ValidationResultFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ValidationRules\ShippingMethodValidationRule;
use Secomm\Ahamove\Model\ErrorMessageManager;

/**
 * Class AfterValidatePlugin
 */
class AfterValidatePlugin
{
    public function __construct(
        protected ValidationResultFactory $validationResultFactory,
        protected ErrorMessageManager     $errorMessageManager
    ) {
    }

    /**
     * Custom message for validation
     *
     * @param ShippingMethodValidationRule $subject
     * @param array $result
     * @param Quote $quote
     * @return array
     */
    public function afterValidate(
        ShippingMethodValidationRule $subject,
        array                        $result,
        Quote                        $quote
    ): array {
        if ($this->errorMessageManager->hasError()) {
            $generalMessage = $this->errorMessageManager->getErrorMessage();
        } else {
            return $result;
        }
        $validationErrors = [__($generalMessage)];
        return [$this->validationResultFactory->create(['errors' => $validationErrors])];
    }
}
