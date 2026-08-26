<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Model\Provider;

use Magento\Directory\Helper\Data as DirectoryData;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FEAT-CSWYEJ / DEC-FEATCSWYEJ-002 — builds the VNPAY checkout URL inside
 * Payment Core. The Vnpayment_VNPAY module is third-party and stays pristine;
 * this builder re-implements its URL contract (params, HMAC-SHA512 signing,
 * TxnRef = increment id — DEC D6) reading the extension's own config paths,
 * without importing any of its classes.
 */
class VnpayCheckoutUrl
{
    private const CONFIG_PAYMENT_URL = 'payment/vnpay/payment_url';
    private const CONFIG_TMN_CODE = 'payment/vnpay/tmn_code';
    private const CONFIG_HASH_CODE = 'payment/vnpay/hash_code';

    private const CURRENCY_VND = 'VND';

    /** Vnpayment_VNPAY return route (Pay controller) — string contract, no class import */
    private const RETURN_URL_PATH = 'paymentvnpay/order/pay';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly DirectoryData $directoryData,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Fresh signed checkout URL for a still-payable order, or null when the
     * gateway is unconfigured or the amount cannot be converted to VND.
     */
    public function build(OrderInterface $order): ?string
    {
        $gatewayUrl = trim((string)$this->scopeConfig->getValue(self::CONFIG_PAYMENT_URL));
        $tmnCode = trim((string)$this->scopeConfig->getValue(self::CONFIG_TMN_CODE));
        $secureSecret = trim((string)$this->scopeConfig->getValue(self::CONFIG_HASH_CODE));
        if ($gatewayUrl === '' || $tmnCode === '' || $secureSecret === '') {
            return null;
        }
        $vnpAmount = $this->toVndX100($order);
        if ($vnpAmount === null) {
            return null;
        }

        $incrementId = (string)$order->getIncrementId();
        $returnUrl = rtrim((string)$this->storeBaseUrl(), '/') . '/' . self::RETURN_URL_PATH;

        $inputData = [
            'vnp_Version' => '2.1.0',
            'vnp_TmnCode' => $tmnCode,
            'vnp_Amount' => $vnpAmount,
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => date('YmdHis'),
            'vnp_CurrCode' => self::CURRENCY_VND,
            'vnp_IpAddr' => $this->clientIp(),
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => $incrementId,
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_TxnRef' => $incrementId,
        ];
        ksort($inputData);
        $query = [];
        $hashData = '';
        $first = true;
        foreach ($inputData as $key => $value) {
            $pair = urlencode($key) . '=' . urlencode((string)$value);
            $hashData = $first ? $pair : $hashData . '&' . $pair;
            $first = false;
            $query[] = $pair;
        }
        $secureHash = hash_hmac('sha512', $hashData, $secureSecret);
        return $gatewayUrl . '?' . implode('&', $query) . '&vnp_SecureHash=' . $secureHash;
    }

    /**
     * Same conversion semantics as the extension's Helper\Rate (order currency
     * → VND, ×100 for VNPAY), self-contained via Magento_Directory.
     */
    private function toVndX100(OrderInterface $order): ?int
    {
        $amount = (float)$order->getTotalDue();
        if ((string)$order->getOrderCurrencyCode() !== self::CURRENCY_VND) {
            try {
                $amount = (float)$this->directoryData->currencyConvert(
                    $amount,
                    (string)$order->getOrderCurrencyCode(),
                    self::CURRENCY_VND
                );
            } catch (\Exception) {
                return null;
            }
        }
        return (int)round($amount * 100);
    }

    /**
     * Current store base URL (cron context falls back to the default store).
     */
    private function storeBaseUrl(): string
    {
        try {
            return (string)$this->storeManager->getStore()->getBaseUrl();
        } catch (\Exception) {
            return '';
        }
    }

    /**
     * Client IP for vnp_IpAddr (defaults when unavailable, e.g. cron).
     */
    private function clientIp(): string
    {
        return isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }
}
