<?php

namespace Secomm\VNPAY\Controller\Order;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceServiceFactory;
use Secomm\VNPAY\Logger\Logger;

/**
 * IPN (server-to-server) — the source of truth for the VNPAY flow.
 *
 * vnp_TxnRef is the reserved order id of a payment attempt on the quote (the
 * order may NOT exist yet). On response code '00' the order is placed from
 * the quote; any other code has no order to cancel.
 */
class Ipn extends Action
{
    public function __construct(
        Context $context,
        private readonly Order $order,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Logger $logger,
        private readonly InvoiceServiceFactory $invoiceServiceFactory,
        private readonly TransactionFactory $transactionFactory,
        private readonly CartManagementInterface $cartManagement,
        private readonly QuoteCollectionFactory $quoteCollectionFactory,
        private readonly OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
    }

    /**
     * Order success action
     *
     * @return void
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
        $returnData = [];
        $secureHash = hash_hmac('sha512', $hashData, $SECURE_SECRET);
        try {
            if ($secureHash == $vnp_SecureHash) {
                $vnp_TxnRef = $this->getRequest()->getParam('vnp_TxnRef', '000000000');
                $vnp_Amount = $this->getRequest()->getParam('vnp_Amount');
                $order = $this->order->loadByIncrementId($vnp_TxnRef);
                if (!$order->getId()) {
                    $quote = $this->quoteCollectionFactory->create()
                        ->addFieldToFilter('reserved_order_id', $vnp_TxnRef)
                        ->addFieldToFilter('is_active', 1)
                        ->getFirstItem();
                    if ($quote->getId() && $vnp_ResponseCode == '00') {
                        $orderId = $this->cartManagement->placeOrder($quote->getId());
                        $order = $this->orderRepository->get($orderId);
                    } elseif ($quote->getId()) {
                        $returnData['RspCode'] = '00';
                        $returnData['Message'] = 'Confirm Success';
                        $this->logger->debug("rspCode: " . $returnData['RspCode'] . " - msg:" . $returnData['Message']);
                        echo json_encode($returnData);
                        return;
                    }
                }
                $orderTotal = (int)($order->getBaseGrandTotal() * 100);
                if ($order->getId()) {
                    if ((int)$vnp_Amount !== $orderTotal) {
                        $returnData['RspCode'] = '04';
                        $returnData['Message'] = 'Invalid amount';
                    } elseif ($order->getStatus() != null && $order->getStatus() == 'pending') {
                        if ($vnp_ResponseCode == '00') {
                            $amount = $this->getRequest()->getParam('vnp_Amount', '0');
                            $setupStatus = $this->scopeConfig->getValue('payment/vnpay/order_status');
                            if ($setupStatus == Order::STATE_PROCESSING) {
                                $order->setTotalPaid(floatval($amount) / 100);
                                $orderState = $order::STATE_PROCESSING;
                                $order->setState($orderState)->setStatus(Order::STATE_PROCESSING);
                                $this->orderRepository->save($order);
                            }
                            if ($order->canInvoice()) {
                                /** @var \Magento\Sales\Model\Service\InvoiceService $invoiceService */
                                $invoiceService = $this->invoiceServiceFactory->create();
                                $invoice = $invoiceService->prepareInvoice($order);
                                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                                $invoice->register();

                                // Save the invoice
                                $transaction = $this->transactionFactory->create();
                                $transactionSave = $transaction
                                    ->addObject($invoice)
                                    ->addObject($invoice->getOrder());

                                $transactionSave->save();
                            }
                        } else {
                            $amount = $this->getRequest()->getParam('vnp_Amount', '0');
                            $order->setTotalPaid(floatval($amount) / 100);
                            $order->addStatusHistoryComment(__('Transaction failed'));
                            $orderState = $order::STATE_CANCELED;
                            $order->setState($orderState)->setStatus(Order::STATE_CANCELED);
                            $this->orderRepository->save($order);
                        }
                        $returnData['RspCode'] = '00';
                        $returnData['Message'] = 'Confirm Success';
                    } else {
                        $returnData['RspCode'] = '02';
                        $returnData['Message'] = 'Order already confirmed';
                    }
                } else {
                    $returnData['RspCode'] = '01';
                    $returnData['Message'] = 'Order not found';
                }
            } else {
                $returnData['RspCode'] = '97';
                $returnData['Message'] = 'Invalid checksum';
            }
        } catch (\Exception $e) {
            $returnData['RspCode'] = '99';
            $returnData['Message'] = 'Unknown error';
        }
        $this->logger->debug("rspCode: " . $returnData['RspCode'] . " - msg:" . $returnData['Message']);
        // Return the response to VNPAY in JSON format
        echo json_encode($returnData);
    }
}