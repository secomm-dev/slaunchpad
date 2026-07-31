<?php
/**
 * Builds the MoMo refund (/v2/gateway/api/refund) request payload.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Secomm\MoMo\Gateway\Config\Config;
use Secomm\MoMo\Gateway\Helper\Signature;
use Secomm\MoMo\Gateway\Response\TransactionHandler;

class RefundBuilder implements BuilderInterface
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var Signature
     */
    private Signature $signature;

    /**
     * Constructor
     *
     * @param Config $config
     * @param Signature $signature
     */
    public function __construct(
        Config $config,
        Signature $signature
    ) {
        $this->config = $config;
        $this->signature = $signature;
    }

    /**
     * Build the MoMo refund payload.
     *
     * @param array $buildSubject
     * @return array<string, scalar>
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();

        $amount = (int)round((float)SubjectReader::readAmount($buildSubject));
        $orderId = (string)$order->getOrderIncrementId();
        $transId = (string)$payment->getAdditionalInformation(TransactionHandler::TRANS_ID);
        $requestId = $orderId . '-refund-' . time();
        $description = (string)__('Refund for order #%1', [$orderId]);

        // rawSignature MUST follow MoMo's exact refund field order.
        $rawParams = [
            'accessKey' => $this->config->getAccessKey(),
            'amount' => $amount,
            'description' => $description,
            'orderId' => $orderId,
            'partnerCode' => $this->config->getPartnerCode(),
            'requestId' => $requestId,
            'transId' => $transId,
        ];

        return [
            'partnerCode' => $this->config->getPartnerCode(),
            'accessKey' => $this->config->getAccessKey(),
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'transId' => $transId,
            'description' => $description,
            'signature' => $this->signature->sign($rawParams, $this->config->getSecretKey()),
            'lang' => 'vi',
        ];
    }
}
