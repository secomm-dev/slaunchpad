<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Secomm\CodRisk\Model\CodRiskList;
use Secomm\CodRisk\Model\CodRiskListFactory;
use Secomm\CodRisk\Model\Audit\AuditLog;
use Secomm\CodRisk\Model\Audit\AuditWriter;
use Secomm\CodRisk\Model\Phone\PhoneNormalizer;

/**
 * Create/update/activate/deactivate phone list records (admin + order-view quick
 * actions). Deactivation is preferred over deletion so the audit trail stays
 * intact (mockup Flow E).
 */
class ListManager
{
    public function __construct(
        private readonly CodRiskListFactory $listFactory,
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly AuditWriter $auditWriter,
    ) {
    }

    /**
     * @param array<string, mixed> $data Keys: list_type, raw_phone, website_id,
     *                                   reason, note, source, effective_from, effective_to, created_by
     */
    public function saveRecord(array $data, ?int $listId = null): CodRiskList
    {
        $normalized = $this->phoneNormalizer->normalize((string)($data['raw_phone'] ?? ''));
        if ($normalized === null) {
            throw new LocalizedException(new Phrase('Phone number is not a valid Vietnamese number.'));
        }

        $listType = strtoupper((string)($data['list_type'] ?? ''));
        if (!in_array($listType, [CodRiskList::LIST_TYPE_BLOCK, CodRiskList::LIST_TYPE_ALLOW], true)) {
            throw new LocalizedException(new Phrase('List type must be BLOCK or ALLOW.'));
        }

        /** @var CodRiskList $record */
        $record = $listId !== null
            ? $this->listFactory->create()->load($listId)
            : $this->listFactory->create();

        $isNew = $record->getId() === null;

        $record->setData([
            'normalized_phone' => $normalized,
            'list_type' => $listType,
            'website_id' => (int)($data['website_id'] ?? 0),
            'reason' => (string)($data['reason'] ?? ''),
            'note' => (string)($data['note'] ?? ''),
            'source' => (string)($data['source'] ?? CodRiskList::SOURCE_ADMIN),
            'effective_from' => $this->toDate($data['effective_from'] ?? null),
            'effective_to' => $this->toDate($data['effective_to'] ?? null),
            'is_active' => (int)($record->getData('is_active') ?? 1),
            'created_by' => (string)($data['created_by'] ?? ''),
        ]);
        $record->save();

        $this->auditWriter->log(
            AuditLog::ENTITY_TYPE_LIST,
            (int)$record->getId(),
            $isNew ? 'created' : 'updated',
            $isNew ? null : $listType,
            sprintf('%s %s (%s)', $listType, $normalized, (string)($data['reason'] ?? '')),
            (string)($data['note'] ?? '')
        );

        return $record;
    }

    public function setActive(int $listId, bool $active): CodRiskList
    {
        /** @var CodRiskList $record */
        $record = $this->listFactory->create()->load($listId);
        if ($record->getId() === null) {
            throw new LocalizedException(new Phrase('List record does not exist.'));
        }

        $record->setData('is_active', $active ? 1 : 0);
        $record->save();

        $this->auditWriter->log(
            AuditLog::ENTITY_TYPE_LIST,
            $listId,
            $active ? 'activated' : 'deactivated',
            $active ? 'inactive' : 'active',
            $active ? 'active' : 'inactive',
            sprintf('%s %s', (string)$record->getData('list_type'), (string)$record->getData('normalized_phone'))
        );

        return $record;
    }

    private function toDate(mixed $value): ?string
    {
        if ($value === null || (string)$value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string)$value)
            ?: \DateTimeImmutable::createFromFormat('d/m/Y', (string)$value);

        return $date !== false ? $date->format('Y-m-d') : null;
    }
}
