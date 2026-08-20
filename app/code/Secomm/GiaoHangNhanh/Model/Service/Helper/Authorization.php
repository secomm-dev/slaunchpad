<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Service\Helper;

use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class Authorization
 *
 * @package Secomm\GiaoHangNhanh\Model\Service\Helper
 */
class Authorization
{
    /**
     * @var string
     */
    protected $params;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * Authorization constructor.
     * @param SerializerInterface $serializer
     */
    public function __construct(
        SerializerInterface $serializer
    ) {
        $this->serializer = $serializer;
    }

    /**
     * @return string
     */
    public function getParameter()
    {
        return $this->params;
    }

    /**
     * @param $params
     * @return $this
     */
    public function setParameter($params)
    {
        $this->params = $this->serializer->serialize($params);
        return $this;
    }

    /**
     * @param $params
     * @return bool|string
     */
    public function getBody($params)
    {
        return $this->serializer->serialize($params);
    }

    /**
     * Get Header
     *
     * @param mixed $params
     * @return array
     */
    public function getHeaders($params = null)
    {
        $headers = [
            'Content-Type: application/json',
        ];

        if (is_array($params)) {
            if (isset($params['token'])) {
                $headers[] = 'token: ' .$params['token'];
            }
            if (isset($params['shop_id'])) {
                $headers[] = 'ShopId: ' .$params['shop_id'];
            }
        }

        return $headers;
    }
}
