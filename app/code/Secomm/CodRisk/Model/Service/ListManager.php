<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Phrase;
use Secomm\CodRisk\Model\CodRiskList;
use Secomm\CodRisk\Model\CodRiskListFactory;
use Secomm\CodRisk\Model\ResourceModel\CodRiskList\CollectionFactory;
use Secomm\CodRisk\Model\Audit\AuditLog;
use Secomm\CodRisk\Model\Audit\AuditWriter;
use Secomm\CodRisk\Model\Phone\PhoneNormalizer;
use Secomm\CodRisk\Model\Reason\ReasonCatalog;

/**
 * Create/update/activate/deactivate phone list records (admin + order-view quick
 * actions). Deactivation is preferred over deletion so the audit trail stays
 * intact (mockup Flow E).
 */
class ListManager
{
    public function __construct(
        private readonly CodRiskListFactory $listFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly ReasonCatalog $reasonCatalog,
        private readonly AuditWriter $auditWriter,
        private readonly TimezoneInterface $localeDate,
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

        // Status is editable from the form when provided; quick-add flows
        // (order view / new records without the field) keep existing or default to active.
        $newIsActive = isset($data['is_active']) && $data['is_active'] !== ''
            ? ((int)$data['is_active'] === 1 ? 1 : 0)
            : (int)($record->getData('is_active') ?? 1);
        $wasActive = $isNew ? 0 : (int)($record->getData('is_active') ?? 0);

        // Effective datetime: picked in the website's config timezone, stored UTC —
        // the core Date grid column then converts back for display, exactly like
        // updated_at (UX review 01/10: full datetime, timezone-correct).
        $effectiveWebsiteId = (int)($data['website_id'] ?? 0);
        $effectiveFrom = $this->toDateTime($data['effective_from'] ?? null, $effectiveWebsiteId);
        $effectiveTo = $this->toDateTime($data['effective_to'] ?? null, $effectiveWebsiteId);

        // One active record per phone + website (Bug 3/4, 2026-09-22): duplicates of
        // the same type are meaningless, and a second type is silently dead weight
        // because Blacklist always outranks Allowlist.
        // The check only applies when the record ENDS UP active and was not already
        // occupying that slot (new, or activating an inactive one). Editing an
        // already-active record — reason/status/notes/dates, including deactivating
        // legacy duplicates — must always stay possible.
        if ($newIsActive === 1 && ($isNew || $wasActive !== 1)) {
            $this->assertNoActiveConflict(
                $normalized,
                (int)($data['website_id'] ?? 0),
                $listType,
                $record->getId() !== null ? (int)$record->getId() : null
            );
        }

        // addData (merge) — setData(array) REPLACES the whole data array and wipes
        // entity_id on a loaded model, turning every edit into an INSERT (new row).
        $record->addData([
            'normalized_phone' => $normalized,
            'list_type' => $listType,
            'website_id' => (int)($data['website_id'] ?? 0),
            'reason' => (string)($data['reason'] ?? ''),
            'note' => (string)($data['note'] ?? ''),
            'source' => (string)($data['source'] ?? CodRiskList::SOURCE_ADMIN),
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'is_active' => $newIsActive,
            'created_by' => (string)($data['created_by'] ?? ''),
        ]);
        $record->save();

        $this->auditWriter->log(
            AuditLog::ENTITY_TYPE_LIST,
            (int)$record->getId(),
            $isNew ? 'created' : 'updated',
            $isNew ? null : $listType,
            sprintf('%s %s (%s)', $listType, $normalized, $this->reasonCatalog->getLabel((string)($data['reason'] ?? ''))),
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

        // Activating must respect the same one-active-record-per-phone+website rule
        // as the form path — the row action bypasses saveRecord otherwise.
        if ($active && (int)($record->getData('is_active') ?? 0) !== 1) {
            $this->assertNoActiveConflict(
                (string)$record->getData('normalized_phone'),
                (int)($record->getData('website_id') ?? 0),
                (string)$record->getData('list_type'),
                $listId
            );
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

    /**
     * Blocks saving an active record when the same phone already has one covering
     * the same website scope:
     * - same type => plain duplicate (Bug 3)
     * - other type => dead/confusing record since Blacklist outranks Allowlist (Bug 4)
     * Website 0 ("All Websites") overlaps with every specific website — a record
     * on 0 + a new record on 1 would be the same enforcement twice.
     * Deactivated records do not block re-adding the phone.
     *
     * @throws LocalizedException
     */
    private function assertNoActiveConflict(string $phone, int $websiteId, string $listType, ?int $excludeId): void
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('normalized_phone', $phone)
            ->addFieldToFilter('website_id', ['in' => array_values(array_unique([0, $websiteId]))])
            ->addFieldToFilter('is_active', 1);
        if ($excludeId !== null) {
            $collection->addFieldToFilter('entity_id', ['neq' => $excludeId]);
        }
        $collection->setPageSize(1);

        $conflict = $collection->getFirstItem();
        if (!$conflict->getId()) {
            return;
        }

        $existingType = (string)$conflict->getData('list_type');
        if ($existingType === $listType) {
            throw new LocalizedException(new Phrase(
                'This phone number already exists in the %1 list for this website.',
                [$listType]
            ));
        }

        throw new LocalizedException(new Phrase(
            'This phone already has an active %1 record for this website. Deactivate or edit the existing record first.',
            [$existingType]
        ));
    }

    /**
     * Normalizes the picked datetime and stores it in UTC: picked value is in the
     * website's config timezone; the grid Date column converts back for display.
     * createFromFormat MUST anchor the config timezone — without it the value is
     * parsed in the PHP default TZ (UTC in this container) and the +7 shift on
     * display would resurrect the phantom-hour bug.
     */
    private function toDateTime(mixed $value, ?int $websiteId): ?string
    {
        if ($value === null || (string)$value === '') {
            return null;
        }

        $value = (string)$value;
        $tzName = $this->localeDate->getConfigTimezone(
            ScopeInterface::SCOPE_WEBSITE,
            (string)($websiteId ?? 0)
        );
        $configTz = new \DateTimeZone($tzName);

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $configTz)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i', $value, $configTz)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d', $value, $configTz);
        if ($date === false) {
            return null;
        }

        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
