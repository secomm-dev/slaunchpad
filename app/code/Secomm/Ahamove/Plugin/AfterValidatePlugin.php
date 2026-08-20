<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Plugin;

use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Validation\ValidationResultFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ValidationRules\ShippingMethodValidationRule;
use Secomm\Ahamove\Model\ErrorMessageManager;

/**
 * Class AfterValidatePlugin
 *
 * @deprecated ErrorMessageManager is being phased out in favor of
 *             Magento's native MessageManagerInterface. This plugin now
 *             forwards any pending Ahamove error session message into
 *             the standard Magento message pool so it surfaces on the
 *             next page render.
 */
class AfterValidatePlugin
{
    public function __construct(
        protected ValidationResultFactory $validationResultFactory,
        protected ErrorMessageManager     $errorMessageManager,
        protected ManagerInterface        $messageManager
    ) {
    }

    /**
     * Forward any pending Ahamove error into both the validation result
     * and the Magento message pool.
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
            $validationErrors = [__($generalMessage)];
            $result[] = $this->validationResultFactory->create(['errors' => $validationErrors]);
            $this->messageManager->addErrorMessage($generalMessage);
        }
        return $result;
    }
}
