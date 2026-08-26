<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Model\Lifecycle;

use Magento\Framework\Stdlib\DateTime\DateTime as DateLib;
use Psr\Log\LoggerInterface;
use Secomm\PaymentCore\Api\Data\PaymentInterface;
use Secomm\PaymentCore\Api\PaymentRepositoryInterface;
use Secomm\PaymentCore\Model\Config;
use Secomm\PaymentCore\Model\ResourceModel\Payment\CollectionFactory;

/**
 * FEAT-CSWYEJ — expiry cron entry (group secomm_paymentcore, every 5 minutes).
 *
 * Deliberately does NOT read the enabled flag (DEC D5 snapshot semantics):
 * disabling the core only stops NEW records; existing active records are still
 * drained to completion. To halt everything, disable the module.
 *
 * This class only batches and delegates; per-order logic lives in
 * CancelExpiredOrder (testability, spec §4.1). The console command
 * paymentcore:expire:run (TASK-Q2BAHW) drives the same methods for QC.
 */
class ExpirePayments
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly CancelExpiredOrder $cancelExpiredOrder,
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly Config $config,
        private readonly DateLib $dateLib,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Cron entry point. One bad record never kills the batch (AC-012).
     * Optional order filter is used by the console command for QC.
     *
     * @return int number of records processed
     */
    public function execute(?int $orderId = null): int
    {
        $now = $this->dateLib->gmtDate('Y-m-d H:i:s');
        $forceCloseCutoff = $this->forceCloseCutoff();

        $records = $this->getCandidates($orderId);
        if ($records === []) {
            return 0;
        }
        $this->logger->info(sprintf(
            '[expire_candidate] %d expired record(s) before %s%s',
            count($records),
            $now,
            $orderId !== null ? sprintf(' (filtered to order %d)', $orderId) : ''
        ));

        $processed = 0;
        foreach ($records as $record) {
            /** @var PaymentInterface $record */
            try {
                if ($forceCloseCutoff !== null
                    && $record->getCreatedAt() !== null
                    && $record->getCreatedAt() < $forceCloseCutoff
                ) {
                    $this->forceClose($record);
                    $processed++;
                    continue;
                }
                $this->cancelExpiredOrder->process($record);
                $processed++;
            } catch (\Exception $exception) {
                $this->logger->error(sprintf(
                    '[cancel_failed] record %d — %s',
                    $record->getEntityId(),
                    $exception->getMessage()
                ));
            }
        }
        return $processed;
    }

    /**
     * Active records past their expires_at snapshot, oldest first, batch-capped.
     * When $orderId is given the expiry-window filter is dropped (QC targets one
     * known order deliberately) but status=active is still enforced.
     *
     * @param int|null $orderId
     * @return PaymentInterface[]
     */
    public function getCandidates(?int $orderId = null): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(PaymentInterface::STATUS, PaymentInterface::STATUS_ACTIVE);
        if ($orderId !== null) {
            $collection->addFieldToFilter(PaymentInterface::ORDER_ID, $orderId);
        } else {
            $now = $this->dateLib->gmtDate('Y-m-d H:i:s');
            $collection->addFieldToFilter(PaymentInterface::EXPIRES_AT, ['lt' => $now]);
            $collection->setOrder(PaymentInterface::EXPIRES_AT, $collection::SORT_ORDER_ASC)
                ->setPageSize($this->config->getBatchSize())
                ->setCurPage(1);
        }
        return array_values($collection->getItems());
    }

    /**
     * D3b — records stuck active for days are marked error (never cancel the
     * order) so the batch moves on and ops can pick them up from the log.
     */
    private function forceClose(PaymentInterface $record): void
    {
        $record->setStatus(PaymentInterface::STATUS_ERROR)
            ->setResolvedAt($this->dateLib->gmtDate('Y-m-d H:i:s'));
        $this->paymentRepository->save($record);
        $this->logger->error(sprintf(
            '[force_close] record %d (order %d) — stuck active longer than force-close window; order NOT canceled',
            $record->getEntityId(),
            $record->getOrderId()
        ));
    }

    /**
     * Y-m-d H:i:s cutoff for force-close, or null when disabled.
     */
    private function forceCloseCutoff(): ?string
    {
        $days = $this->config->getForceCloseDays();
        if ($days <= 0) {
            return null;
        }
        return $this->dateLib->gmtDate('Y-m-d H:i:s', $this->dateLib->gmtTimestamp() - $days * 86400);
    }
}
