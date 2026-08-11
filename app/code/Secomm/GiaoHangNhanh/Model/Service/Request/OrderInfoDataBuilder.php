<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Service\Request;

use Secomm\GiaoHangNhanh\Model\Service\Helper\SubjectReader;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class OrderInfoDataBuilder
 *
 * @package Secomm\GiaoHangNhanh\Model\Service\Request
 */
class OrderInfoDataBuilder extends AbstractDataBuilder
{
    /**
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     */
    public function build(array $buildSubject)
    {
        $order = SubjectReader::readOrder($buildSubject);
        return [
            self::TOKEN => $this->config->getValue('api_token'),
            self::ORDER_CODE => $order->getData('tracking_code')
        ];
    }
}
