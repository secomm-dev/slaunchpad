<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Service;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Secomm\CodRisk\Api\CodRiskEvaluatorInterface;
use Secomm\CodRisk\Api\Data\CodRiskDecisionInterface;
use Secomm\CodRisk\Model\CodRiskList;
use Secomm\CodRisk\Model\Config;
use Secomm\CodRisk\Model\Data\CodRiskContext;
use Secomm\CodRisk\Model\Phone\PhoneNormalizer;
use Secomm\CodRisk\Model\Service\EventCounter;
use Secomm\CodRisk\Model\ResourceModel\CodRiskEvent\CollectionFactory as EventCollectionFactory;
use Secomm\CodRisk\Model\ResourceModel\CodRiskEvaluation\CollectionFactory as EvaluationCollectionFactory;
use Secomm\CodRisk\Model\ResourceModel\AuditLog\CollectionFactory as AuditCollectionFactory;

/**
 * Phone Inspector aggregation (mockup Flow B): one screen answering
 * "what is this phone's state, why, and what can I do next".
 */
class PhoneInspector
{
    private const RELATED_LIMIT = 50;

    public function __construct(
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly CodRiskEvaluatorInterface $evaluator,
        private readonly ListLookup $listLookup,
        private readonly EventCollectionFactory $eventCollectionFactory,
        private readonly EvaluationCollectionFactory $evaluationCollectionFactory,
        private readonly AuditCollectionFactory $auditCollectionFactory,
        private readonly EventCounter $eventCounter,
        private readonly Config $config,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
    }

    /**
     * @return array|null null when no phone was entered.
     */
    public function inspect(?string $rawPhone, ?int $websiteId): ?array
    {
        if (trim((string)$rawPhone) === '') {
            return null;
        }

        $normalized = $this->phoneNormalizer->normalize($rawPhone);

        $result = [
            'raw_phone' => (string)$rawPhone,
            'normalized_phone' => $normalized,
            'decision' => null,
            'historical_count' => 0,
            'spam_matched' => false,
            'blacklist' => null,
            'allowlist' => null,
            'events' => [],
            'evaluations' => [],
            'audit' => [],
        ];

        if ($normalized === null) {
            return $result;
        }

        $context = new CodRiskContext($this->phoneNormalizer, $rawPhone, $websiteId);
        $result['decision'] = $this->evaluator->evaluate($context);

        // Same fix as OrderRiskView: pipeline short-circuits on higher-precedence
        // rules, so the displayed counts are computed directly from the event store.
        $websiteId ??= null;
        $historicalSince = new \DateTimeImmutable(
            '-' . $this->config->getLookbackDays($websiteId) . ' days',
            new \DateTimeZone('UTC')
        );
        $result['historical_count'] = $this->eventCounter->countEvents(
            $normalized,
            $websiteId,
            $historicalSince,
            null,
            includeOnly: true
        );
        $result['spam_matched'] = $this->isSpamMatched($normalized, $websiteId);
        $result['blacklist'] = $this->listLookup->findActive(
            $normalized,
            $websiteId,
            CodRiskList::LIST_TYPE_BLOCK
        );
        $result['allowlist'] = $this->listLookup->findActive(
            $normalized,
            $websiteId,
            CodRiskList::LIST_TYPE_ALLOW
        );
        $result['events'] = $this->relatedEvents($normalized, $websiteId);
        // Must run AFTER events exist — resolves increment ids for the rows above.
        $result['order_increments'] = $this->resolveOrderIncrements($result['events']);
        $result['evaluations'] = $this->relatedEvaluations($normalized, $websiteId);
        $result['audit'] = $this->relatedAudit($normalized);

        return $result;
    }

    private function relatedEvents(string $phone, ?int $websiteId): array
    {
        $collection = $this->eventCollectionFactory->create();
        $collection->addFieldToFilter('normalized_phone', $phone);
        if ($websiteId !== null) {
            $collection->addFieldToFilter('website_id', ['in' => array_unique([0, $websiteId])]);
        }
        $collection->setOrder('created_at', 'DESC')->setPageSize(self::RELATED_LIMIT);

        return $collection->getItems();
    }

    private function relatedEvaluations(string $phone, ?int $websiteId): array
    {
        $collection = $this->evaluationCollectionFactory->create();
        $collection->addFieldToFilter('normalized_phone', $phone);
        if ($websiteId !== null) {
            $collection->addFieldToFilter('website_id', ['in' => array_unique([0, $websiteId])]);
        }
        $collection->setOrder('created_at', 'DESC')->setPageSize(self::RELATED_LIMIT);

        return $collection->getItems();
    }

    private function relatedAudit(string $phone): array
    {
        $collection = $this->auditCollectionFactory->create();
        $collection->addFieldToFilter(['old_value', 'new_value', 'note'], [
            ['like' => '%' . $phone . '%'],
            ['like' => '%' . $phone . '%'],
            ['like' => '%' . $phone . '%'],
        ]);
        $collection->setOrder('created_at', 'DESC')->setPageSize(self::RELATED_LIMIT);

        return $collection->getItems();
    }

    /**
     * Order View links show human increment ids (#000012345), never raw entity ids.
     *
     * @param array $events
     * @return array<int, string> order entity_id => increment_id
     */
    private function resolveOrderIncrements(array $events): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($event) => (int)($event->getData('order_id') ?? 0),
            $events
        ))));

        if ($ids === []) {
            return [];
        }

        $criteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $ids, 'in')
            ->create();

        $map = [];
        foreach ($this->orderRepository->getList($criteria)->getItems() as $order) {
            $map[(int)$order->getEntityId()] = (string)$order->getIncrementId();
        }

        return $map;
    }

    private function isSpamMatched(string $phone, ?int $websiteId): bool
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
}
