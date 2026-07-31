<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Service\Response;

use Secomm\GiaoHangNhanh\Model\Service\Helper\SubjectReader;
use Secomm\IntegrationBase\Model\Service\Response\HandlerInterface;
use Magento\Sales\Model\Order;

/**
 * Class SynchronizeOrderHandler
 *
 * @package Secomm\GiaoHangNhanh\Model\Service\Response
 */
class SynchronizeOrderHandler implements HandlerInterface
{
    const GHN_STATUS_FAIL = 0;
    const GHN_STATUS_SUCCESS = 1;

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
