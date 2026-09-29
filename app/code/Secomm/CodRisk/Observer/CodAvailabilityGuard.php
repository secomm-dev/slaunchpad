<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Psr\Log\LoggerInterface;
use Secomm\CodRisk\Api\CodRiskEvaluatorInterface;
use Secomm\CodRisk\Api\Data\CodRiskDecisionInterface;
use Secomm\CodRisk\Model\Config;
use Secomm\CodRisk\Model\Data\CodRiskContext;
use Secomm\CodRisk\Model\Phone\PhoneNormalizer;

/**
 * Server-side gate at order placement (sales_model_service_quote_submit_before).
 *
 * The availability plugin only refreshes the rendered payment list — OSC does not
 * reload it on every phone edit, so a blocked phone can still reach place-order
 * with a stale list. This gate is the authoritative last line: COD order placement
 * is refused with the admin-configurable message whenever the decision is BLOCK.
 * Fail-open on evaluator errors, consistent with the availability plugin.
 */
class CodAvailabilityGuard implements ObserverInterface
{
    public function __construct(
        private readonly CodRiskEvaluatorInterface $evaluator,
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var \Magento\Quote\Model\Quote|null $quote */
        $quote = $observer->getEvent()->getData('quote');
        if ($quote === null || $quote->getPayment() === null) {
            return;
        }
        if ($quote->getPayment()->getMethod() !== 'cashondelivery') {
            return;
        }

        $websiteId = (int)$quote->getStore()->getWebsiteId();
        if (!$this->config->isEnabled($websiteId)) {
            return;
        }

        try {
            $phone = (string)$quote->getShippingAddress()->getTelephone();
            $decision = $this->evaluator->evaluate(new CodRiskContext(
                $this->phoneNormalizer,
                $phone,
                $websiteId,
                (int)$quote->getId(),
                null,
                $quote->getCustomerId() !== null ? (int)$quote->getCustomerId() : null
            ));
        } catch (\Throwable $e) {
            $this->logger->critical(
                sprintf('[CodRisk] Place-order guard failed open: %s', $e->getMessage()),
                ['exception' => $e]
            );

            return;
        }

        if ($decision->getDecision() === CodRiskDecisionInterface::BLOCK) {
            $this->logger->info(sprintf(
                '[CodRisk] COD order placement refused for phone %s (quote %d, rule: %s)',
                (string)$decision->getNormalizedPhone(),
                (int)$quote->getId(),
                (string)$decision->getMatchedRule()
            ));
            throw new LocalizedException(
                new Phrase($this->config->getCheckoutMessage($websiteId))
            );
        }
    }
}