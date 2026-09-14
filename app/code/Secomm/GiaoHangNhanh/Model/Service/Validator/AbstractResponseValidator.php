<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Service\Validator;

use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\ConfigInterface;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Validator\AbstractValidator;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Validator\ResultInterfaceFactory;

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
        ConfigInterface $config = null
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

    /**
     * Extract the real error message from a GHN error response
     * (e.g. "Kích thước (dài) vượt quá mức cho phép: 150").
     *
     * @param array $response
     * @return string
     */
    protected function getGhnErrorMessage(array $response): string
    {
        return $response[self::MSG]
            ?: ($response['code_message_value'] ?? '')
            ?: '';
    }
}
