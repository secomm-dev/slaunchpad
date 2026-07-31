<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Service\Validator;

use Secomm\IntegrationBase\Model\Service\ConfigInterface;
use Secomm\IntegrationBase\Model\Service\Validator\AbstractValidator;
use Secomm\IntegrationBase\Model\Service\Validator\ResultInterfaceFactory;

/**
 * Class AbstractResponseValidator
 *
 * @package Secomm\GiaoHangNhanh\Model\Service\Validator
 */
abstract class AbstractResponseValidator extends AbstractValidator
{
    const MSG = 'message';
    const SUCCESS_MESSAGE = 'Success';

    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * AbstractResponseValidator constructor.
     * @param ResultInterfaceFactory $resultFactory
     * @param ConfigInterface|null $config
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        ?ConfigInterface $config = null
    ) {
        parent::__construct($resultFactory);
        $this->config = $config;
    }

    /**
     * @param array $response
     * @return bool
     */
    protected function validateResponseMsg(array $response)
    {
        return isset($response[self::MSG]) && $response[self::MSG] === self::SUCCESS_MESSAGE;
    }
}
