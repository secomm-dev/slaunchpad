<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Service\Response;

use Secomm\GiaoHangNhanh\Model\Service\Helper\SubjectReader;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Response\HandlerInterface;
use Magento\Sales\Model\Order;

/**
 * Class SynchronizeOrderHandler
 *
 * @package Secomm\GiaoHangNhanh\Model\Service\Response
 */
class SynchronizeOrderHandler implements HandlerInterface
{
    const GHN_STATUS_FAIL = 'failed';
    const GHN_STATUS_SUCCESS = 'ready_to_pick';

    /**
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle(array $handlingSubject, array $response)
    {
        /** @var Order $order */
        $order = SubjectReader::readOrder($handlingSubject);
        $responseData = SubjectReader::readResponseData($response);

        if ($trackingCode = SubjectReader::readOrderCode($responseData)) {
            $order->setData('ghn_status', self::GHN_STATUS_SUCCESS);
            $order->setData('tracking_code', $trackingCode);
        } else {
            $order->setData('ghn_status', self::GHN_STATUS_FAIL);
        }
        $order->save();
    }
}
