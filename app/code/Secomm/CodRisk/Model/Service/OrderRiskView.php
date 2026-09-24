<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Service;

use Magento\Sales\Api\Data\OrderInterface;
use Secomm\CodRisk\Api\CodRiskEvaluatorInterface;
use Secomm\CodRisk\Model\CodRiskList;
use Secomm\CodRisk\Model\CodRiskOverride;
use Secomm\CodRisk\Model\CodRiskOverrideFactory;
use Secomm\CodRisk\Model\Config;
use Secomm\CodRisk\Model\Data\CodRiskContext;
use Secomm\CodRisk\Model\Phone\PhoneNormalizer;
use Secomm\CodRisk\Model\ResourceModel\CodRiskEvent\CollectionFactory as EventCollectionFactory;
use Secomm\CodRisk\Model\Service\EventCounter;

/**
 * Builds the Order View COD Risk section payload (D-06 option A, D-07).
 *
 * S-03: evaluates LIVE (ALLOW traces are not persisted), and the latest
 * per-order override — if any — is applied on top of the base decision.
 */
class OrderRiskView
{
    private const EVENT_LIMIT = 5;

    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly CodRiskEvaluatorInterface $evaluator,
        private readonly ListLookup $listLookup,
        private readonly CodRiskOverrideFactory $overrideFactory,
        private readonly EventCollectionFactory $eventCollectionFactory,
        private readonly EventCounter $eventCounter,
        private readonly Config $config,
    ) {
    }

    /**
     * @return array{
     *     phone_raw: string, phone: ?string, base_decision: ?CodRiskDecisionInterface,
     *     decision: ?CodRiskDecisionInterface, override: ?CodRiskOverride,
     *     blacklist: ?CodRiskList, allowlist: ?CodRiskList, events: array
     * }
     */
    public function getSummary(OrderInterface $order): array
    {
        $rawPhone = (string)$order->getShippingAddress()?->getTelephone();
        $normalized = $this->phoneNormalizer->normalize($rawPhone);
        $websiteId = (int)$order->getStore()->getWebsiteId();

        $summary = [
            'phone_raw' => $rawPhone,
            'phone' => $normalized,
            'base_decision' => null,
            'decision' => null,
            'override' => null,
            'blacklist' => null,
            'allowlist' => null,
            'events' => [],
        ];

        if ($normalized === null) {
            return $summary;
        }

        $baseDecision = $this->evaluator->evaluate(new CodRiskContext(
            $this->phoneNormalizer,
            $rawPhone,
            $websiteId,
            null,
            (int)$order->getEntityId(),
            $order->getCustomerId() !== null ? (int)$order->getCustomerId() : null
        ));

        $override = $this->resolveOverride((int)$order->getEntityId());

        $summary['base_decision'] = $baseDecision;
        $summary['decision'] = $baseDecision;
        // The template renders the override annotation (base BLOCK -> ALLOW) when
        // present; the underlying decision data stays untouched (CR-007).

        $summary['blacklist'] = $this->listLookup->findActive($normalized, $websiteId, CodRiskList::LIST_TYPE_BLOCK);
        $summary['allowlist'] = $this->listLookup->findActive($normalized, $websiteId, CodRiskList::LIST_TYPE_ALLOW);
        $summary['events'] = $this->recentEvents($normalized, $websiteId);

        // Display stats are computed independently of the pipeline: when a
        // higher-precedence rule (Blacklist/Spam) terminates evaluation, the
        // Historical rule never runs — the section must still show the true
        // counts instead of a misleading 0.
        $summary['historical_count'] = $this->computeHistoricalCount($normalized, $websiteId);
        $summary['spam_matched'] = $this->computeSpamMatched($normalized, $websiteId);

        return $summary;
    }

    private function computeHistoricalCount(string $phone, int $websiteId): int
    {
        $since = new \DateTimeImmutable(
            '-' . $this->config->getLookbackDays($websiteId) . ' days',
            new \DateTimeZone('UTC')
        );

        return $this->eventCounter->countEvents($phone, $websiteId, $since, null, includeOnly: true);
    }

    private function computeSpamMatched(string $phone, int $websiteId): bool
    {
        if (!$this->config->isSpamEnabled($websiteId)) {
            return false;
        }

        $since = new \DateTimeImmutable(
            '-' . $this->config->getSpamLookbackDays($websiteId) . ' days',
            new \DateTimeZone('UTC')
        );
        $count = $this->eventCounter->countEvents(
            $phone,
            $websiteId,
            $since,
            $this->config->getSpamAttributableReasons($websiteId)
        );

        return $count >= $this->config->getSpamThreshold($websiteId);
    }

    private function resolveOverride(int $orderId): ?CodRiskOverride
    {
        $collection = $this->overrideFactory->create()->getCollection();
        $collection->addFieldToFilter('order_id', $orderId);
        $collection->setOrder('created_at', 'DESC')->setPageSize(1);

        $item = $collection->getFirstItem();

        return $item->getId() ? $item : null;
    }

    private function recentEvents(string $phone, int $websiteId): array
    {
        $collection = $this->eventCollectionFactory->create();
        $collection->addFieldToFilter('normalized_phone', $phone)
            ->addFieldToFilter('website_id', ['in' => array_unique([0, $websiteId])])
            ->setOrder('created_at', 'DESC')
            ->setPageSize(self::EVENT_LIMIT);

        return $collection->getItems();
    }
}
