<?php

declare(strict_types=1);

namespace Secomm\VNPAY\Cron;

use Magento\Sales\Api\OrderRepositoryInterface;
use Secomm\VNPAY\Logger\Logger;
use Secomm\VNPAY\Model\ResourceModel\VnpayRefund\CollectionFactory as VnpayRefundCollectionFactory;
use Secomm\VNPAY\Model\VnpayRefund;
use Secomm\VNPAY\Model\VnpayQueryService;

/**
 * Cron every 15 minutes — asks VNPAY (QueryDR) for the status of refund
 * requests without a final result and updates vn_pay_refund + order comment.
 *
 * VNPAY statuses: 05 processing → 06 sent to bank / 09 refund rejected.
 * 06 and 09 are final — finalized rows are no longer picked.
 */
class RefundStatusSync
{
    private const FINAL_STATUSES = ['06', '09'];
    private const MAX_AGE_DAYS = 3;
    private const BATCH_LIMIT = 50;

    public function __construct(
        private readonly VnpayRefundCollectionFactory $refundCollectionFactory,
        private readonly VnpayQueryService $queryService,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Logger $logger
    ) {
    }

    public function execute(): void
    {
        $pending = $this->refundCollectionFactory->create()
            ->addFieldToFilter('response_code', ['in' => ['00', '94']])
            ->addFieldToFilter('vnp_transaction_status', [
                ['null' => true],
                ['nin' => self::FINAL_STATUSES],
            ])
            ->setOrder('created_at', 'ASC')
            ->setPageSize(self::BATCH_LIMIT);

        if ((int)$pending->getSize() === 0) {
            return;
        }

        $this->logger->info(sprintf(
            'VNPAY refund sync: %d pending refunds to check',
            (int)$pending->getSize()
        ));

        foreach ($pending as $row) {
            try {
                $this->syncRow($row);
            } catch (\Throwable $exception) {
                $this->logger->error(sprintf(
                    'VNPAY refund sync: failed for ref %s — %s',
                    (string)$row->getTxnRef(),
                    $exception->getMessage()
                ));
            }
        }
    }

    private function syncRow(VnpayRefund $row): void
    {
        $createdAt = strtotime((string)$row->getData('created_at'));
        if ($createdAt !== false && $createdAt < strtotime('-' . self::MAX_AGE_DAYS . ' days')) {
            // Infinite-poll guard: no final status after 3 days → check the
            // VNPAY portal manually (bank refunds take 1–3 days).
            $this->logger->critical(sprintf(
                'VNPAY refund sync: ref %s (order %s) has no final status after %d days — check the VNPAY portal manually',
                (string)$row->getTxnRef(),
                (string)$row->getIncrementId(),
                self::MAX_AGE_DAYS
            ));
            return;
        }

        $result = $this->queryService->queryRefundStatus(
            (string)$row->getTxnRef(),
            (string)$row->getTransactionNo(),
            (string)$row->getPayDate()
        );

        if ($result['response_code'] !== '00') {
            $this->logger->error(sprintf(
                'VNPAY refund sync: querydr returned %s (%s) for ref %s',
                $result['response_code'],
                $result['message'],
                (string)$row->getTxnRef()
            ));
            return;
        }

        $status = $result['transaction_status'];
        if ($status !== '' && $status !== (string)$row->getVnpTransactionStatus()) {
            $row->setVnpTransactionStatus($status);
            $row->save();
            $this->logger->info(sprintf(
                'VNPAY refund sync: ref %s status → %s',
                (string)$row->getTxnRef(),
                $status
            ));
        }

        if ($status === '06') {
            // 06 = VNPAY has sent the refund request to the bank — final result
            // from VNPAY (money reaches the customer 1–3 days later at the bank).
            $order = $this->orderRepository->get((int)$row->getOrderId());
            $order->addCommentToStatusHistory(__('VNPAY: refund sent to the bank (transaction status 06).'));
            $this->orderRepository->save($order);
        } elseif ($status === '09') {
            $order = $this->orderRepository->get((int)$row->getOrderId());
            $order->addCommentToStatusHistory(__('VNPAY: refund was REJECTED by the bank (transaction status 09) — manual action required.'));
            $this->orderRepository->save($order);
            $this->logger->critical(sprintf(
                'VNPAY: refund REJECTED (09) for order %s (ref %s) — manual action required',
                (string)$row->getIncrementId(),
                (string)$row->getTxnRef()
            ));
        }
    }
}
