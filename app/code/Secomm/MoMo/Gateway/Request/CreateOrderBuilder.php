<?php
/**
 * Builds the MoMo "create order" (/v2/gateway/api/create) request payload.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Secomm\MoMo\Model\Config;
use Secomm\MoMo\Gateway\Helper\Signature;

class CreateOrderBuilder implements BuilderInterface
{
    /**
     * Constructor
     *
     * @param Config $config
     * @param Signature $signature
     */
    public function __construct(
        private readonly Config $config,
        private readonly Signature $signature
    ) {
    }

    /**
     * Build the MoMo create-order payload.
     *
     * @param array $buildSubject
     * @return array<string, scalar>
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder();

        $orderId = (string)$order->getOrderIncrementId();
        $requestId = $orderId . '-' . time();
        // Amount passed by the Redirect controller (order total due), cast to
        // integer minor units. MoMo only settles VND; the quote currency is
        // validated to VND before this method runs.
        $amount = (int)SubjectReader::readAmount($buildSubject);
        $extraData = base64_encode((string)$order->getEntityId());
        // Encode text fields so characters like & = cannot break the rawSignature.
        $orderInfo = (string)__('Payment for order #%1', $orderId);

        // rawSignature MUST follow MoMo's exact field order. Text values are
        // URL-encoded so reserved characters (&, =) cannot alter the signature.
        $rawParams = [
            'accessKey' => $this->config->getAccessKey(),
            'amount' => $amount,
            'extraData' => $extraData,
            'ipnUrl' => $this->config->getNotifyUrl(),
            'orderId' => $orderId,
            'orderInfo' => rawurlencode($orderInfo),
            'partnerCode' => $this->config->getPartnerCode(),
            'redirectUrl' => $this->config->getReturnUrl(),
            'requestId' => $requestId,
            'requestType' => Config::KEY_REQUEST_TYPE,
        ];

        return [
            'partnerCode' => $this->config->getPartnerCode(),
            'accessKey' => $this->config->getAccessKey(),
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $this->config->getReturnUrl(),
            'ipnUrl' => $this->config->getNotifyUrl(),
            'extraData' => $extraData,
            'requestType' => Config::KEY_REQUEST_TYPE,
            'signature' => $this->signature->sign($rawParams, $this->config->getSecretKey()),
            'lang' => 'vi',
        ];
    }
}
