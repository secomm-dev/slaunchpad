<?php

namespace Secomm\VNPAY\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\CartLockedException;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Secomm\VNPAY\Logger\Logger;

/**
 * Browser return URL from VNPAY.
 *
 * If the IPN has already processed the payment the order exists by
 * vnp_TxnRef and the customer is sent to the success page. If the IPN has
 * not arrived (e.g. local sandbox) the order is placed here from the quote.
 * On failure the cart is kept intact for a retry.
 */
class Pay extends Action
{
    public function __construct(
        Context $context,
        private readonly Order $order,
        private readonly \Magento\Checkout\Model\Session $checkoutSession,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Logger $logger,
        private readonly CartManagementInterface $cartManagement,
        private readonly QuoteCollectionFactory $quoteCollectionFactory,
        private readonly OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Order success action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $vnp_SecureHash = $this->getRequest()->getParam('vnp_SecureHash', '');
        $SECURE_SECRET = $this->scopeConfig->getValue('payment/vnpay/hash_code');
        $responseParams = $this->getRequest()->getParams();
        $vnp_ResponseCode = $this->getRequest()->getParam('vnp_ResponseCode', '');
        $inputData = [];
        foreach ($responseParams as $key => $value) {
            $inputData[$key] = $value;
        }
        unset($inputData['vnp_SecureHashType']);
        unset($inputData['vnp_SecureHash']);
        ksort($inputData);
        $i = 0;
        $hashData = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashData, $SECURE_SECRET);
        if ($secureHash == $vnp_SecureHash) {
            if ($vnp_ResponseCode == '00') {
                $txnRef = $this->getRequest()->getParam('vnp_TxnRef');
                $order = $this->order->loadByIncrementId($txnRef);
                if (!$order->getId()) {
                    $quote = $this->quoteCollectionFactory->create()
                        ->addFieldToFilter('reserved_order_id', $txnRef)
                        ->addFieldToFilter('is_active', 1)
                        ->getFirstItem();
                    if ($quote->getId()) {
                        try {
                            $orderId = $this->cartManagement->placeOrder($quote->getId());
                            $order = $this->orderRepository->get($orderId);
                        } catch (CartLockedException $e) {
                            // The IPN callback is placing the same order
                            // concurrently (CartMutex serializes placeOrder per
                            // quote with timeout 0). Wait for the IPN to finish
                            // and re-check once before giving up.
                            $this->logger->info('VNPAY: place order locked by the concurrent IPN process, re-checking: ' . $e->getMessage());
                            sleep(2);
                            $order = $this->order->loadByIncrementId($txnRef);
                        } catch (\Exception $e) {
                            $this->logger->error('VNPAY place order error: ' . $e->getMessage());
                        }
                    }
                }
                if ($order->getId()) {
                    // The order was placed outside the checkout session
                    // lifecycle (Ipn/Pay) — reset the stale quote binding
                    // before populating success session data.
                    $this->checkoutSession->clearQuote();
                    $this->checkoutSession->clearHelperData();
                    $this->checkoutSession->setLastQuoteId($order->getQuoteId());
                    $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                    $this->checkoutSession->setLastOrderId($order->getId());
                    $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
                } else {
                    // Payment succeeded at VNPAY but the order could not be
                    // placed — manual reconciliation required.
                    $this->logger->critical('VNPAY: payment successful but no order was created (ref ' . $txnRef . ') — manual reconciliation required');
                    $this->messageManager->addErrorMessage(__('Your payment was received but the order could not be created. Please contact us for assistance.'));
                    $this->clearStaleOrderSession();
                    return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                }
                $this->messageManager->addSuccessMessage(__('Payment successful'));
                return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
            } else {
                $this->messageManager->addErrorMessage(__('Payment failed'));
                $this->logger->error("responseCode: $vnp_ResponseCode - msg: " . $this->getResponseDescription($vnp_ResponseCode));
                $this->clearStaleOrderSession();
                $this->restoreCart();
                return $this->resultRedirectFactory->create()->setPath('checkout/cart');
            }
        } else {
            $this->messageManager->addErrorMessage(__('Payment failed'));
            $this->logger->error("msg: " . __("Invalid signature"));
            $this->clearStaleOrderSession();
            $this->restoreCart();
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }
    }

    public function getResponseDescription($responseCode)
    {
        switch ($responseCode) {
            case "00" :
                $result = __("Transaction successful");
                break;
            case "01" :
                $result = __("The transaction already exists");
                break;
            case "02" :
                $result = __("Invalid merchant (check the vnp_TmnCode)");
                break;
            case "03" :
                $result = __("Invalid request data format");
                break;
            case "04" :
                $result = __("Transaction initiation failed because the website is temporarily locked");
                break;
            case "05" :
                $result = __("Transaction failed: incorrect password entered too many times. Please try again");
                break;
            case "06" :
                $result = __("Transaction failed: incorrect OTP verification password. Please try again");
                break;
            case "07" :
                $result = __("Transaction is suspected to be fraudulent");
                break;
            case "09" :
                $result = __("Transaction failed: card/account has not registered for Internet banking at the bank");
                break;
            case "10" :
                $result = __("Transaction failed: incorrect card/account verification more than 3 times");
                break;
            case "11" :
                $result = __("Transaction failed: payment waiting period has expired. Please try again");
                break;
            case "12" :
                $result = __("Transaction failed: card/account is locked");
                break;
            case "24" :
                $result = __("Transaction failed: customer canceled the transaction");
                break;
            case "51" :
                $result = __("Transaction failed: insufficient account balance");
                break;
            case "65" :
                $result = __("Transaction failed: daily transaction limit has been exceeded");
                break;
            case "75" :
                $result = __("The payment bank is under maintenance");
                break;
            case "79" :
                $result = __("Transaction failed: incorrect payment password entered too many times");
                break;
            case "99" :
                $result = __("An error occurred during the transaction");
                break;
            default :
                $result = __("Transaction failed");
        }
        return $result;
    }

    /**
     * Keep the cart when payment failed
     */
    private function restoreCart()
    {
        $txnRef = $this->getRequest()->getParam('vnp_TxnRef');
        if (!$txnRef) {
            return;
        }

        $order = $this->order->loadByIncrementId($txnRef);
        if ($order->getId()
            && $order->getPayment()->getMethod() == 'vnpay'
            && in_array($order->getStatus(), ['pending', 'closed', 'canceled'], true)
        ) {
            $this->checkoutSession->restoreQuote();
        }
    }

    /**
     * Remove previous successful order data from checkout session on payment failure.
     */
    private function clearStaleOrderSession()
    {
        $this->checkoutSession->clearHelperData();
        $this->checkoutSession->setLastSuccessQuoteId(null);
    }
}
