<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Plugin;

use Magento\OfflinePayments\Model\Cashondelivery;
use Magento\Quote\Api\Data\CartInterface;
use Psr\Log\LoggerInterface;
use Secomm\CodRisk\Api\CodRiskEvaluatorInterface;
use Secomm\CodRisk\Api\Data\CodRiskDecisionInterface;
use Secomm\CodRisk\Model\Config;
use Secomm\CodRisk\Model\Data\CodRiskContext;
use Secomm\CodRisk\Model\Phone\PhoneNormalizer;

/**
 * COD availability asks CodRisk (AD-01) — merged into CodRisk 2026-09-21
 * (single-module decision; boundary kept: risk module plugs into payment
 * availability, core payment logic untouched).
 *
 * Mapping (SPEC-TASK-YPWH9B §2): ALLOW/WARNING => COD stays available,
 * BLOCK => COD hidden. Only COD is affected — other payment methods never pass
 * through here. Fail-open by design: any evaluator failure keeps the core
 * availability result and logs critical (checkout must not break over risk data).
 */
class CashOnDeliveryAvailabilityPlugin
{
    public function __construct(
        private readonly CodRiskEvaluatorInterface $evaluator,
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function afterIsAvailable(Cashondelivery $subject, bool $result, ?CartInterface $quote = null): bool
    {
        if (!$result || $quote === null) {
            return $result;
        }

        $websiteId = (int)$quote->getStore()->getWebsiteId();
        if (!$this->config->isEnabled($websiteId)) {
            return $result;
        }

        try {
            $phone = (string)$quote->getShippingAddress()->getTelephone();
            $context = new CodRiskContext(
                $this->phoneNormalizer,
                $phone,
                $websiteId,
                (int)$quote->getId(),
                null,
                $quote->getCustomerId() !== null ? (int)$quote->getCustomerId() : null
            );

            $decision = $this->evaluator->evaluate($context);

            if ($decision->getDecision() === CodRiskDecisionInterface::BLOCK) {
                // Traceability: every checkout-side COD hiding is logged with the reason.
                $this->logger->info(sprintf(
                    '[CodRisk] COD hidden for phone %s (website %d, rule: %s, historical count: %d)',
                    (string)$decision->getNormalizedPhone(),
                    $websiteId,
                    (string)$decision->getMatchedRule(),
                    $decision->getHistoricalCount()
                ));

                return false;
            }

            return $result;
        } catch (\Throwable $e) {
            // Risk data must never take checkout down (plan §4 regression risk) —
            // deliberately fail-open with a critical trace instead of rethrowing.
            $this->logger->critical(
                sprintf('[CodRisk] COD availability evaluation failed, failing open: %s', $e->getMessage()),
                ['exception' => $e]
            );

            return $result;
        }
    }
}
