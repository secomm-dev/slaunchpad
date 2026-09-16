<?php

namespace Secomm\VNPAY\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Quote\Model\QuoteRepository;
use Magento\Store\Model\StoreManagerInterface;
use Secomm\VNPAY\Helper\Rate;

/**
 * Creates a VNPAY payment attempt for the current checkout quote and returns
 * the VNPAY payment URL. No order is created here — the order is placed by
 * Ipn/Pay only after VNPAY confirms a successful payment.
 */
class Info extends Action
{
    public function __construct(
        Context $context,
        private readonly Json $jsonFac,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly \Magento\Checkout\Model\Session $checkoutSession,
        private readonly Rate $helperRate,
        private readonly QuoteRepository $quoteRepository,
        private readonly RemoteAddress $remoteAddress
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $quote = $this->checkoutSession->getQuote();
        if ($quote->getId() && !$quote->getIsActive()) {
            // Stale quote from a previous VNPAY attempt (consumed by Ipn/Pay) —
            // start a fresh cart so the customer is not stuck on the old quote.
            $this->checkoutSession->clearQuote();
            $quote = $this->checkoutSession->getQuote();
        }
        $url = $this->scopeConfig->getValue('payment/vnpay/payment_url');
        $vnp_Url = '';
        if ($quote->getId() && $quote->getIsActive()) {
            $quote->getPayment()->setMethod('vnpay');
            // ALWAYS reserve a FRESH order id for every payment attempt —
            // VNPAY locks a vnp_TxnRef after repeated failures (code 79 after
            // wrong OTP attempts), so a retry must never reuse the previous
            // attempt's reference.
            $quote->setReservedOrderId(null);
            $quote->reserveOrderId();
            $quote->collectTotals();
            $this->quoteRepository->save($quote);

            $incrementID = $quote->getReservedOrderId();

            $amount = $quote->getGrandTotal();
            $vnpAmount = round(($this->helperRate->getVndAmountByCurrency($quote->getQuoteCurrencyCode(), $amount) * 100), 0);

            $returnUrl = $this->storeManager->getStore()->getBaseUrl();
            $returnUrl = rtrim($returnUrl, "/");
            $returnUrl .= "/paymentvnpay/order/pay";
            $inputData = [
                "vnp_Version" => "2.1.0",
                "vnp_TmnCode" => $this->scopeConfig->getValue('payment/vnpay/tmn_code'),
                "vnp_Amount" => $vnpAmount,
                "vnp_Command" => "pay",
                "vnp_CreateDate" => date('YmdHis'),
                "vnp_CurrCode" => "VND",
                "vnp_IpAddr" => $this->remoteAddress->getRemoteAddress(),
                "vnp_Locale" => 'vn',
                "vnp_OrderInfo" => $incrementID,
                "vnp_OrderType" => 'other',
                "vnp_ReturnUrl" => $returnUrl,
                "vnp_TxnRef" => $incrementID,
            ];
            ksort($inputData);
            $query = "";
            $i = 0;
            $hashdata = "";
            foreach ($inputData as $key => $value) {
                if ($i == 1) {
                    $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
                } else {
                    $hashdata .= urlencode($key) . "=" . urlencode($value);
                    $i = 1;
                }
                $query .= urlencode($key) . "=" . urlencode($value) . '&';
            }

            $vnp_Url = $url . "?" . $query;
            $SECURE_SECRET = $this->scopeConfig->getValue('payment/vnpay/hash_code');
            if (isset($SECURE_SECRET)) {
                $vnpSecureHash = hash_hmac('sha512', $hashdata, $SECURE_SECRET);
                $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
            }
        }
        $this->jsonFac->setData($vnp_Url);
        return $this->jsonFac;
    }
}