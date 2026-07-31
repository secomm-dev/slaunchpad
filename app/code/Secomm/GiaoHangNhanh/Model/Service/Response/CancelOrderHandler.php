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
use Secomm\GiaoHangNhanh\Model\Integration\Response\HandlerInterface;
use Magento\Sales\Model\Order;

/**
 * Class CancelOrderHandler
 *
 * @package Secomm\GiaoHangNhanh\Model\Service\Response
 */
class CancelOrderHandler implements HandlerInterface
{
    const GHN_SUCCESS_CANCELING_STATUS = 1;

    /**
     * @param array $handlingSubject
     * @param array $response
     */
    public function handle(array $handlingSubject, array $response)
    {
        /** @var Order $order */
        $order = SubjectReader::readOrder($handlingSubject);
        $order->setData('ghn_canceling_status', self::GHN_SUCCESS_CANCELING_STATUS);
    }
}
