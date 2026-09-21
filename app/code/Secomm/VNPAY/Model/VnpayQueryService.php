<?php

declare(strict_types=1);

namespace Secomm\VNPAY\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\VNPAY\Logger\Logger;

/**
 * VNPAY QueryDR client (vnp_Command = querydr — techspec 2.1.0).
 *
 * Asks VNPAY for the actual status of a refund request (async).
 * Final states: vnp_TransactionStatus 05 processing → 06 sent to
 * bank / 09 refund rejected.
 */
class VnpayQueryService
{
    private const CONFIG_PATH_PREFIX = 'payment/vnpay/';

    /**
     * Checksum field order per VNPAY spec for QueryDR (fields joined by "|").
     */
    private const CHECKSUM_FIELDS = [
        'vnp_RequestId',
        'vnp_Version',
        'vnp_Command',
        'vnp_TmnCode',
        'vnp_TxnRef',
        'vnp_TransactionDate',
        'vnp_CreateDate',
        'vnp_IpAddr',
        'vnp_OrderInfo',
    ];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RemoteAddress $remoteAddress,
        private readonly Curl $curl,
        private readonly Logger $logger,
        private readonly TimezoneInterface $timezone,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Asks VNPAY for the current status of a refund request.
     *
     * @param string $txnRef — the original payment vnp_TxnRef
     * @param string $transactionNo — vnp_TransactionNo (optional per spec)
     * @param string $payDate — original payment date (GMT+7, yyyyMMddHHmmss)
     * @return array{response_code: string, transaction_status: string, message: string}
     * @throws LocalizedException when the response cannot be parsed
     */
    public function queryRefundStatus(string $txnRef, string $transactionNo, string $payDate): array
    {
        // VNPAY requires GMT+7 — uses the store timezone configured in Admin.
        $timeZone = $this->timezone->getConfigTimezone(
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getCode()
        );
        $now = new \DateTimeImmutable('now', new \DateTimeZone($timeZone));

        $request = [
            'vnp_RequestId'       => 'QRY' . $now->format('YmdHis'),
            'vnp_Version'         => (string)($this->getConfig('version') ?: '2.1.0'),
            'vnp_Command'         => 'querydr',
            'vnp_TmnCode'         => (string)$this->getConfig('tmn_code'),
            'vnp_TxnRef'          => $txnRef,
            'vnp_TransactionDate' => $payDate,
            'vnp_CreateDate'      => $now->format('YmdHis'),
            'vnp_IpAddr'          => (string)$this->remoteAddress->getRemoteAddress(),
            'vnp_OrderInfo'       => 'Truy van hoan tien ' . $txnRef,
        ];
        if ($transactionNo !== '') {
            $request['vnp_TransactionNo'] = $transactionNo; // optional per spec
        }

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
        $this->curl->addHeader('User-Agent', 'Secomm-VNPAY/1.0');
        $this->curl->setOptions([CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,]);
        $this->curl->post(
            (string)$this->getConfig('api_url'),
            json_encode($request, JSON_UNESCAPED_SLASHES)
        );

        $response = json_decode($this->curl->getBody() ?: '{}', true) ?? [];
        $this->logger->info(sprintf(
            'VNPAY querydr: txn_ref %s — HTTP status %d, response %s',
            $txnRef,
            $this->curl->getStatus(),
            $this->curl->getBody()
        ));

        return [
            'response_code'      => (string)($response['vnp_ResponseCode'] ?? ''),
            'transaction_status' => (string)($response['vnp_TransactionStatus'] ?? ''),
            'message'            => (string)($response['vnp_Message'] ?? ''),
        ];
    }

    private function getConfig(string $field): ?string
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_PREFIX . $field);
    }
}
