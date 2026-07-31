<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Secomm Integration
 */
namespace Secomm\GiaoHangNhanh\Model\Integration\Validator;

use Secomm\GiaoHangNhanh\Model\Integration\Validator\ResultInterfaceFactory;

/**
 * Class AbstractValidator
 * @package Secomm\GiaoHangNhanh\Model\Integration\Validator
 */
abstract class AbstractValidator implements ValidatorInterface
{
    /**
     * @var ResultInterfaceFactory
     */
    private $resultInterfaceFactory;

    /**
     * @param ResultInterfaceFactory $resultFactory
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory
    ) {
        $this->resultInterfaceFactory = $resultFactory;
    }

    /**
     * Factory method
     *
     * @param bool $isValid
     * @param array $fails
     * @param array $errorCodes
     * @return \Magento\Payment\Gateway\Validator\ResultInterface
     */
    protected function createResult($isValid, array $fails = [], array $errorCodes = [])
    {
        return $this->resultInterfaceFactory->create(
            [
                'isValid' => (bool)$isValid,
                'failsDescription' => $fails,
                'errorCodes' => $errorCodes
            ]
        );
    }
}
