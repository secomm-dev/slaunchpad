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
use Magento\Sales\Model\Order\Creditmemo;
use Psr\Log\LoggerInterface;
use Secomm\Tracking\Model\Config;
use Secomm\Tracking\Model\Consent\ConsentEvaluatorInterface;
use Secomm\Tracking\Model\Delivery\EnqueueService;
use Secomm\Tracking\Model\Event\EventNormalizer;

/**
 * FEAT-31X6N2 / SPEC §9 — refund event per creditmemo, gated by the opt-in
 * `secomm_tracking/refund/enabled` flag (default OFF, AC-006).
 */
class RefundFire implements ObserverInterface
{
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
            if (!$this->config->isRefundEnabled()) {
                return;
            }

            $creditmemo = $observer->getEvent()->getCreditmemo();
            if (!$creditmemo instanceof Creditmemo || !$creditmemo->getId()) {
                return;
            }

            $scopeCode = null; // single-store Phase 1 (BR-TBD-001)
            $event = $this->normalizer->fromCreditmemo($creditmemo);
            $event = $event->withConsent(
                $this->consentEvaluator->allows(ConsentEvaluatorInterface::SCOPE_ANALYTICS, $scopeCode),
                $this->consentEvaluator->allows(ConsentEvaluatorInterface::SCOPE_MARKETING, $scopeCode)
            );

            $this->enqueueService->enqueue($event, $scopeCode);
        } catch (\Throwable $e) {
            // Tracking must never break the creditmemo save.
            $this->logger->error('Secomm Tracking: refund observer failed', ['error' => $e->getMessage()]);
        }
    }
}
