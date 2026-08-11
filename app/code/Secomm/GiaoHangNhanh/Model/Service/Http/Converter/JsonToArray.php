<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Service\Http\Converter;

use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http\ConverterException;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Http\ConverterInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Class JsonToArray
 *
 * @package Secomm\GiaoHangNhanh\Model\Service\Http\Converter
 */
class JsonToArray implements ConverterInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * JsonToArray constructor.
     *
     * @param Json            $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        Json $serializer,
        LoggerInterface $logger
    ) {
        $this->logger     = $logger;
        $this->serializer = $serializer;
    }

    /**
     * Converts gateway response to array structure
     *
     * @param mixed $response
     * @return array
     * @throws ConverterException
     */
    public function convert($response)
    {
        try {
            return $this->serializer->unserialize($response);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
            $this->logger->critical('Can\'t read response from GHN');
            throw new ConverterException(__('Can\'t read response from GHN'));
        }
    }
}
