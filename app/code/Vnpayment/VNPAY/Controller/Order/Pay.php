<?php

namespace Vnpayment\VNPAY\Controller\Order;

use Magento\Framework\App\Action\Context;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;

class Pay extends \Magento\Framework\App\Action\Action {

    /** @var  \Magento\Sales\Model\Order */
    protected $order;

    /** @var  \Magento\Checkout\Model\Session */
    protected $checkoutSession;

    /** @var  \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $scopeConfig;

    /** @var \Vnpayment\VNPAY\Logger\Logger */
    protected $logger;
    protected $quoteFactory;

    /** @var CartManagementInterface */
    protected $cartManagement;

    /** @var QuoteCollectionFactory */
    protected $quoteCollectionFactory;

    public function __construct(
        Context $context,
        \Magento\Sales\Model\Order $order,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Vnpayment\VNPAY\Logger\Logger $logger,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        CartManagementInterface $cartManagement,
        QuoteCollectionFactory $quoteCollectionFactory
    ) {
        parent::__construct($context);
        $this->order = $order;
        $this->checkoutSession = $checkoutSession;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->quoteFactory = $quoteFactory;
        $this->cartManagement = $cartManagement;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
    }

    /**
     * Order success action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute() {
        $vnp_SecureHash = $this->getRequest()->getParam('vnp_SecureHash', '');
        $SECURE_SECRET = $this->scopeConfig->getValue('payment/vnpay/hash_code');
        $responseParams = $this->getRequest()->getParams();
        $vnp_ResponseCode = $this->getRequest()->getParam('vnp_ResponseCode', '');
        $inputData = array();
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
                            $order = $this->order->load($orderId);
                        } catch (\Exception $e) {
                            $this->logger->error('VNPAY place order error: ' . $e->getMessage());
                        }
                    }
                }
                if ($order->getId()) {
                    $this->checkoutSession->setLastQuoteId($order->getQuoteId());
                    $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                    $this->checkoutSession->setLastOrderId($order->getId());
                    $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
                }
                $this->messageManager->addSuccess(__("Thanh toán thành công"));
                return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
            } else {
                $this->messageManager->addError(__("Thanh toán thất bại"));
                $this->logger->error("responseCode: $vnp_ResponseCode - msg: ".$this->getResponseDescription($vnp_ResponseCode));
                $this->clearStaleOrderSession();
                $this->restoreCart();
                return $this->resultRedirectFactory->create()->setPath('checkout/cart');
            }
        } else {
            $this->messageManager->addError(__("Thanh toán thất bại"));
            $this->logger->error("msg: " . __("Chữ ký không hợp lệ"));
            $this->clearStaleOrderSession();
            $this->restoreCart();
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }
    }

    public function getResponseDescription($responseCode) {

        switch ($responseCode) {
            case "00" :
                $result = __("Giao dịch thành công");
                break;
            case "01" :
                $result = __("Giao dịch đã tồn tại");
                break;
            case "02" :
                $result = __("Merchant không hợp lệ (kiểm tra lại vnp_TmnCode)");
                break;
            case "03" :
                $result = __("Dữ liệu gửi sang không đúng định dạng");
                break;
            case "04" :
                $result = __("Khởi tạo giao dịch không thành công do Website đang bị tạm khóa");
                break;
            case "05" :
                $result = __("Giao dịch không thành công do: Quý khách nhập sai mật khẩu quá số lần quy định. Xin quý khách vui lòng thực hiện lại giao dịch");
                break;
            case "06" :
                $result = __("Giao dịch không thành công do Quý khách nhập sai mật khẩu xác thực giao dịch (OTP). Xin quý khách vui lòng thực hiện lại giao dịch");
                break;
            case "07" :
                $result = __("Giao dịch bị nghi ngờ là giao dịch gian lận");
                break;
            case "09" :
                $result = __("Giao dịch không thành công do: Thẻ/Tài khoản của khách hàng chưa đăng ký dịch vụ InternetBanking tại ngân hàng");
                break;
            case "10" :
                $result = __("Giao dịch không thành công do: Khách hàng xác thực thông tin thẻ/tài khoản không đúng quá 3 lần");
                break;
            case "11" :
                $result = __("Giao dịch không thành công do: Đã hết hạn chờ thanh toán. Xin quý khách vui lòng thực hiện lại giao dịch");
                break;
            case "12" :
                $result = __("Giao dịch không thành công do: Thẻ/Tài khoản của khách hàng bị khóa");
                break;
            case "24" :
                $result = __("Giao dịch không thành công do: Khách hàng hủy giao dịch");
                break;
            case "51" :
                $result = __("Giao dịch không thành công do: Tài khoản của quý khách không đủ số dư để thực hiện giao dịch");
                break;
            case "65" :
                $result = __("Giao dịch không thành công do: Tài khoản của Quý khách đã vượt quá hạn mức giao dịch trong ngày");
                break;
            case "75" :
                $result = __("Ngân hàng thanh toán đang bảo trì");
                break;
            case "79" :
                $result = __("Giao dịch không thành công do: Khách hàng nhập sai mật khẩu thanh toán quá số lần quy định. Xin quý khách vui lòng thực hiện lại giao dịch");
                break;
            case "99" :
                $result = __("Có lỗi xảy ra trong quá trình thực hiện giao dịch");
                break;
            default :
                $result = __("Giao dịch thất bại");
        }
        return $result;
    }

    /**
     * Keep cart when failed payment
     * @return void
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
     *
     * @return void
     */
    private function clearStaleOrderSession()
    {
        $this->checkoutSession->clearHelperData();
        $this->checkoutSession->setLastSuccessQuoteId(null);
    }
}
