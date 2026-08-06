<?php
/**
 * Authoritative MoMo payment handler (Notify / IPN).
 *
 * On a successful MoMo result, registers the transaction id, creates an online
 * capture invoice and moves the order to Processing. Idempotent — only acts
 * while the order can still be invoiced.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Response;

use Magento\Framework\DB\TransactionFactory;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;

class TransactionHandler implements HandlerInterface
{
    public const TRANS_ID = 'transId';
    public const RESULT_CODE = 'resultCode';

    /**
     * Constructor
     *
     * @param InvoiceService $invoiceService
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param InvoiceSender $invoiceSender
     * @param TransactionFactory $transactionFactory
     */
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly InvoiceSender $invoiceSender,
        private readonly TransactionFactory $transactionFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();
        /** @var Order $order */
        $order = $payment->getOrder();

        $transId = (string)($response[self::TRANS_ID] ?? '');
        $payment->setAdditionalInformation('momo_trans_id', $transId);

        if ($transId === '') {
            return;
        }

        $payment->setTransactionId($transId);
        $payment->setIsTransactionClosed(false);

        if (!$order->canInvoice()) {
            return; // already invoiced — idempotent.
        }

        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
        $invoice->register();
        $invoice->setTransactionId($transId);
        $invoice->setIsCustomerNotified(true);
        $this->invoiceRepository->save($invoice);

        $payment->setShouldCloseParentTransaction(true);

        $order->addCommentToStatusHistory(
            __('MoMo payment confirmed. Transaction ID: %1', [$transId])
        );

        $transaction = $this->transactionFactory->create();
        $transaction->addObject($invoice)
            ->addObject($order)
            ->save();

        if ($this->invoiceSender->send($invoice)) {
            $order->addCommentToStatusHistory(__('Notified customer about invoice #%1', [$invoice->getIncrementId()]));
        }
    }
}
