<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Secomm Integration
 */
namespace Secomm\IntegrationBase\Model\Service\Validator;

use Magento\Framework\Phrase;

/**
 * Class Result
 * @package Secomm\IntegrationBase\Model\Service\Validator
 */
class Result implements ResultInterface
{
    /**
     * @var bool
     */
    private $isValid;

    /**
     * @var Phrase[]
     */
    private $failsDescription;

    /**
     * @var string[]
     */
    private $errorCodes;

    /**
     * @param bool $isValid
     * @param array $failsDescription
     * @param array $errorCodes
     */
    public function __construct(
        $isValid,
        array $failsDescription = [],
        array $errorCodes = []
    ) {
        $this->isValid = (bool)$isValid;
        $this->failsDescription = $failsDescription;
        $this->errorCodes = $errorCodes;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * {@inheritdoc}
     */
    public function getFailsDescription(): array
    {
        return $this->failsDescription;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorCodes(): array
    {
        return $this->errorCodes;
    }
}
