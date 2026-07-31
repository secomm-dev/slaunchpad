<?php
/**
 * Extracts the MoMo payUrl from a successful create-order response.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class CreateOrderHandler implements HandlerInterface
{
    public const PAY_URL = 'payUrl';
    public const REQUEST_ID = 'requestId';
    public const ORDER_ID = 'orderId';

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        $payment->setAdditionalInformation(self::PAY_URL, $response[self::PAY_URL] ?? '');
        $payment->setAdditionalInformation(self::REQUEST_ID, $response[self::REQUEST_ID] ?? '');
        $payment->setAdditionalInformation(self::ORDER_ID, $response[self::ORDER_ID] ?? '');
    }
}
