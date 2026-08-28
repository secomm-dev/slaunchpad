<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Tracking\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Secomm\Tracking\Model\Config;
use Secomm\Tracking\Model\Consent\ConsentEvaluatorInterface;
use Secomm\Tracking\Model\Delivery\EnqueueService;
use Secomm\Tracking\Model\Event\EventNormalizer;

/**
 * FEAT-31X6N2 / DEC D3 — server purchase event on the order state TRANSITION
 * into a paid state (processing). Fires once per order even when Mollie webhook
 * and VNPAY IPN each trigger their own save: only a transition (orig state ≠ new
 * state) enqueues, and UNIQUE (event_id, vendor) collapses any residual race.
 *
 * Observer is insert-only (EnqueueService); vendor HTTP lives in the flush cron —
 * checkout critical path stays untouched (CODING_RULES external-call rule).
 */
class PurchaseFire implements ObserverInterface
{
    /**
     * States meaning "payment confirmed" for tracking purposes. payment_review
     * is deliberately excluded (pending capture) — Mollie/VNPAY confirm moves
     * the order to processing which fires there.
     */
    private const PAID_STATES = [
        \Magento\Sales\Model\Order::STATE_PROCESSING,
        \Magento\Sales\Model\Order::STATE_COMPLETE,
    ];

    public function __construct(
        private readonly Config $config,
        private readonly EventNormalizer $normalizer,
        private readonly EnqueueService $enqueueService,
        private readonly ConsentEvaluatorInterface $consentEvaluator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        try {
            $order = $observer->getEvent()->getOrder();
            if (!$order instanceof Order || !$order->getId()) {
                return;
            }

            // TEMP DEBUG (remove after diagnosis) — see var/log/secomm_tracking.log
            $this->logger->debug('Secomm Tracking: purchase observer hit', [
                'order' => $order->getIncrementId(),
                'state' => (string)$order->getState(),
                'orig_state' => (string)$order->getOrigData(Order::STATE),
            ]);

            if (!$this->isPaidTransition($order)) {
                return;
            }

            $scopeCode = null; // single-store Phase 1 (BR-TBD-001)
            $event = $this->normalizer->fromOrder($order);
            $event = $event->withConsent(
                $this->consentEvaluator->allows(ConsentEvaluatorInterface::SCOPE_ANALYTICS, $scopeCode),
                $this->consentEvaluator->allows(ConsentEvaluatorInterface::SCOPE_MARKETING, $scopeCode)
            );

            $this->enqueueService->enqueue($event, $scopeCode);
        } catch (\Throwable $e) {
            // Tracking must never break the order save.
            $this->logger->error('Secomm Tracking: purchase observer failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * True only when the state JUST moved into a paid state on this save —
     * replays of the same state (extra saves from webhooks/IPNs) return false.
     */
    private function isPaidTransition(Order $order): bool
    {
        $newState = (string)$order->getState();
        if (!in_array($newState, self::PAID_STATES, true)) {
            return false;
        }

        $origState = (string)$order->getOrigData(Order::STATE);

        return $origState !== $newState;
    }
}
