<?php

declare(strict_types=1);

namespace Secomm\VNPAY\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Sales\Model\Order\Payment;
use Secomm\VNPAY\Logger\Logger;
use Secomm\VNPAY\Model\ResourceModel\VnpayRefund as VnpayRefundResource;

/**
 * VNPAY Refund API client (vnp_Command = refund — techspec 2.1.0 §2.5.5).
 *
 * Refund is ASYNC: response 00/94 only means the request was accepted by
 * VNPAY. The final result must be followed up via QueryDR / VNPAY portal
 * (vnp_TransactionStatus: 05 processing, 06 sent to bank, 09 rejected).
 */
class VnpayRefundService
{
    private const CONFIG_PATH_PREFIX = 'payment/vnpay/';

    /**
     * Checksum field order per VNPAY spec (fields joined by "|").
     */
    private const CHECKSUM_FIELDS = [
        'vnp_RequestId',
        'vnp_Version',
        'vnp_Command',
        'vnp_TmnCode',
        'vnp_TransactionType',
        'vnp_TxnRef',
        'vnp_Amount',
        'vnp_TransactionNo',
        'vnp_TransactionDate',
        'vnp_CreateBy',
        'vnp_CreateDate',
        'vnp_IpAddr',
        'vnp_OrderInfo',
    ];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RemoteAddress $remoteAddress,
        private readonly Curl $curl,
        private readonly Logger $logger,
        private readonly VnpayRefundFactory $refundFactory,
        private readonly VnpayRefundResource $refundResource
    ) {
    }

    /**
     * Sends a VNPAY refund request for the given order payment.
     *
     * @param Payment $payment
     * @param float $amount refund amount in base currency, must be <= the paid amount
     * @throws LocalizedException when VNPAY rejects the refund or original transaction data is missing
     */
    public function refund(Payment $payment, float $amount): void
    {
        $order = $payment->getOrder();
        $info = $order->getPayment();

        $transactionNo = (string)($info->getAdditionalInformation('vnp_transaction_no') ?? '');
        $payDate = (string)($info->getAdditionalInformation('vnp_pay_date') ?? '');
        if ($transactionNo === '' || $payDate === '') {
            throw new LocalizedException(
                __('Missing original VNPAY transaction data (vnp_TransactionNo / vnp_PayDate) — online refund is not possible for this order.')
            );
        }

        // vnp_TransactionType: 02 = full refund, 03 = partial refund.
        $totalPaid = (float)$order->getTotalPaid();
        $transactionType = ($totalPaid > 0 && $amount < $totalPaid) ? '03' : '02';

        // vnp_RequestId / vnp_CreateDate use server time (date()) — same as
        // Info.php. Ensure the server PHP timezone matches VNPAY (GMT+7).
        $now = date('YmdHis');

        $request = [
            'vnp_RequestId'       => 'REQ' . $now,
            'vnp_Version'         => (string)($this->getConfig('version') ?: '2.1.0'),
            'vnp_Command'         => 'refund',
            'vnp_TmnCode'         => (string)$this->getConfig('tmn_code'),
            'vnp_TransactionType' => $transactionType,
            'vnp_TxnRef'          => (string)$order->getIncrementId(),
            'vnp_Amount'          => (string)round($amount * 100),
            'vnp_TransactionNo'   => $transactionNo,
            'vnp_TransactionDate' => $payDate,
            'vnp_CreateBy'        => 'admin',
            'vnp_CreateDate'      => $now,
            'vnp_IpAddr'          => (string)$this->remoteAddress->getRemoteAddress(),
            'vnp_OrderInfo'       => 'Hoan tien don hang ' . $order->getIncrementId(),
        ];

        $checksumData = implode('|', array_map(
            static fn (string $field): string => (string)$request[$field],
            self::CHECKSUM_FIELDS
        ));
        $request['vnp_SecureHash'] = hash_hmac(
            'sha512',
            $checksumData,
            (string)$this->getConfig('hash_code')
        );

        $this->curl->addHeader('Content-Type', 'application/json');
        // VNPAY sandbox WAF may return HTTP 403 for requests without a
        // User-Agent or over IPv6 (IP whitelist is IPv4-based).
        $this->curl->addHeader('User-Agent', 'Secomm-VNPAY/1.0');
        $this->curl->setOptions([
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);
        $this->curl->post(
            (string)$this->getConfig('api_url'),
            json_encode($request, JSON_UNESCAPED_SLASHES)
        );

        $response = json_decode($this->curl->getBody() ?: '{}', true) ?? [];
        $this->logger->info(sprintf(
            'VNPAY refund: order %s, amount %.2f — HTTP status %d, response %s',
            $order->getIncrementId(),
            $amount,
            $this->curl->getStatus(),
            $this->curl->getBody()
        ));

        $responseCode = (string)($response['vnp_ResponseCode'] ?? '');
        if (in_array($responseCode, ['00', '94'], true)) {
            // 00 = request accepted; 94 = refund request already sent, still processing.
            // Tracked for async status follow-up + duplicate refund protection (mirrors zalo_pay_refund).
            // Non-fatal: VNPAY already accepted the refund — a row save error (e.g. missing table because
            // setup:upgrade has not run) must NOT break the credit memo.
            try {
                $this->logger->info(sprintf(
                    'VNPAY: writing vn_pay_refund row (request %s, txn_ref %s)',
                    $request['vnp_RequestId'],
                    $request['vnp_TxnRef']
                ));
                $refundRow = $this->refundFactory->create();
                $refundRow->addData([
                    'order_id' => (int)$order->getId(),
                    'increment_id' => (string)$order->getIncrementId(),
                    'txn_ref' => $request['vnp_TxnRef'],
                    'transaction_no' => $transactionNo,
                    'pay_date' => $payDate,
                    'refund_type' => $transactionType,
                    'amount' => $amount,
                    'request_id' => $request['vnp_RequestId'],
                    'response_code' => $responseCode,
                    'vnp_transaction_status' => (string)($response['vnp_TransactionStatus'] ?? ''),
                ]);
                $this->refundResource->save($refundRow);
                $this->logger->info('VNPAY: vn_pay_refund row saved, id=' . (int)$refundRow->getId());
            } catch (\Throwable $rowException) {
                $this->logger->critical(sprintf(
                    'VNPAY: refund accepted but vn_pay_refund row was NOT saved (ref %s, request %s): %s',
                    $request['vnp_TxnRef'],
                    $request['vnp_RequestId'],
                    $rowException->getMessage()
                ));
            }
            $payment->setLastTransId($transactionNo);
            return;
        }

        throw new LocalizedException(
            __(
                'VNPAY refund failed: %1 (code %2).',
                (string)($response['vnp_Message'] ?? 'Unknown error'),
                $responseCode ?: '??'
            )
        );
    }

    private function getConfig(string $field): ?string
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_PREFIX . $field);
    }
}
