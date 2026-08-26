<?php
/**
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\PaymentCore\Model\Lifecycle;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime as DateLib;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Psr\Log\LoggerInterface;
use Secomm\PaymentCore\Api\Data\PaymentInterface;
use Secomm\PaymentCore\Api\PaymentRepositoryInterface;
use Secomm\PaymentCore\Model\Config;
use Secomm\PaymentCore\Model\PaymentFactory;

/**
 * FEAT-CSWYEJ — snapshot the pending-payment record at place-order time (spec §4.1).
 *
 * Called from the place-end observer. Never throws: a missing record only means
 * the expiry cron will ignore the order (conservative-safe), so checkout must not
 * fail because of Payment Core.
 */
class AssignManagedPayment
{
    public function __construct(
        private readonly Config $config,
        private readonly PaymentRepositoryInterface $repository,
        private readonly PaymentFactory $paymentFactory,
        private readonly DateLib $dateLib,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create the lifecycle record for a just-placed order when its method is managed.
     *
     * @param OrderInterface $order
     * @return void
     */
    public function execute(OrderInterface $order): void
    {
        try {
            if (!$order->getEntityId()) {
                // Runtime-verified 2026-08-25: this fires if the observer is ever
                // wired to a pre-save event again. Log loudly instead of silently
                // dropping the record (previous failure mode).
                $this->logger->warning(sprintf(
                    '[assign] order has no entity_id yet (increment %s) — event fired before save; skipping',
                    $order->getIncrementId() ?? '?'
                ));
                return;
            }
            if (!$this->isAssignable($order)) {
                return;
            }
            /** @var OrderPaymentInterface|null $payment */
            $payment = $order->getPayment();
            if ($payment === null) {
                return;
            }
            $methodCode = (string)$payment->getMethod();
            if ($methodCode === '') {
                return;
            }
            $websiteId = $order->getStore()?->getWebsiteId();
            if (!$this->config->isManagedMethod($methodCode, $websiteId)) {
                return;
            }
            $alreadyTracked = false;
            try {
                $this->repository->getByOrderId((int)$order->getEntityId());
                $alreadyTracked = true;
            } catch (NoSuchEntityException) {
                $alreadyTracked = false;
            }
            if ($alreadyTracked) {
                return;
            }
            $minutes = $this->config->resolveExpiryMinutes($methodCode, $websiteId);
            /** @var PaymentInterface $record */
            $record = $this->paymentFactory->create();
            $record->setOrderId((int)$order->getEntityId())
                ->setStoreId((int)$order->getStoreId())
                ->setMethodCode($methodCode)
                ->setExpiresAt($this->dateLib->gmtDate('Y-m-d H:i:s', $this->dateLib->gmtTimestamp() + $minutes * 60))
                ->setStatus(PaymentInterface::STATUS_ACTIVE);
            $this->repository->save($record);
            $this->logger->info(sprintf(
                '[assign] order %s method %s expires_at %s (+%d min)',
                $order->getIncrementId(),
                $methodCode,
                $record->getExpiresAt(),
                $minutes
            ));
        } catch (\Exception $exception) {
            $this->logger->error(sprintf(
                '[assign] failed for order %s: %s',
                $order->getIncrementId() ?? '?',
                $exception->getMessage()
            ));
        }
    }

    /**
     * Core enabled + order has an entity id.
     */
    private function isAssignable(OrderInterface $order): bool
    {
        if (!$order->getEntityId()) {
            return false;
        }
        $websiteId = $order->getStore()?->getWebsiteId();
        return $this->config->isEnabled($websiteId);
    }
}

