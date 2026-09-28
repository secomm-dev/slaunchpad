<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ShippingCore\Model\Zone;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Secomm\ShippingCore\Api\Address\CanonicalZoneInterface;
use Secomm\ShippingCore\Api\Address\CanonicalZoneRepositoryInterface;
use Secomm\ShippingCore\Cache\Type\Zone as ZoneCacheType;
use Secomm\ShippingCore\Model\Address\CanonicalZone;
use Secomm\ShippingCore\Model\ResourceModel\Zone as ZoneResource;
use Secomm\ShippingCore\Model\ResourceModel\Zone\CollectionFactory;
use Secomm\ShippingCore\Model\Zone as ZoneModel;
use Secomm\ShippingCore\Model\ZoneFactory;

/**
 * FEAT-QA23PZ / DEC-FEATQA23PZ-001 — the ONLY write path for `secomm_shipping_zone`:
 * full saves validate first (rejects, never silently drops) and every mutation flushes the
 * `secomm_shippingcore_zones` cache type so runtime registries observe the new state on the
 * next request. The runtime domain consumes only the frozen `CanonicalZoneInterface` VO —
 * the persistence shape never leaks across this boundary.
 */
class ZoneRepository implements CanonicalZoneRepositoryInterface
{
    private ZoneFactory $zoneFactory;

    private ZoneResource $zoneResource;

    private CollectionFactory $collectionFactory;

    private Validator $validator;

    private TypeListInterface $cacheTypeList;

    public function __construct(
        ZoneFactory $zoneFactory,
        ZoneResource $zoneResource,
        CollectionFactory $collectionFactory,
        Validator $validator,
        TypeListInterface $cacheTypeList
    ) {
        $this->zoneFactory = $zoneFactory;
        $this->zoneResource = $zoneResource;
        $this->collectionFactory = $collectionFactory;
        $this->validator = $validator;
        $this->cacheTypeList = $cacheTypeList;
    }

    public function getAll(): array
    {
        return $this->voFromRows($this->collectionFactory->create()->getItems());
    }

    public function getByCode(string $code): ?CanonicalZoneInterface
    {
        $item = $this->collectionFactory->create()
            ->addFieldToFilter('code', trim($code))
            ->setPageSize(1)
            ->getFirstItem();
        if (!$item->getId()) {
            return null;
        }

        return $this->voFromRow($item);
    }

    public function getEnabledByCodes(array $codes): array
    {
        if ($codes === []) {
            return [];
        }
        $byCode = [];
        foreach ($this->getAll() as $zone) {
            $byCode[$zone->getCode()] = $zone;
        }
        $result = [];
        foreach (array_values(array_unique($codes)) as $code) {
            $zone = $byCode[$code] ?? null;
            if ($zone instanceof CanonicalZoneInterface && $zone->isEnabled()) {
                $result[] = $zone;
            }
        }

        return $result;
    }

    public function save(CanonicalZoneInterface $zone, ?int $zoneId = null): int
    {
        $normalized = $this->validator->validate($zone, $zoneId);
        $model = $zoneId === null
            ? $this->zoneFactory->create()
            : $this->loadById($zoneId);
        $model->setCode($normalized->getCode());
        $model->setLabel($normalized->getLabel());
        $model->setEnabled($normalized->isEnabled());
        $model->setIncludeProvinceCodes($normalized->getIncludeProvinceCodes());
        $model->setIncludeWardCodes($normalized->getIncludeWardCodes());
        $model->setExcludeWardCodes($normalized->getExcludeWardCodes());
        $this->zoneResource->save($model);
        $this->flushCache();

        return (int) $model->getId();
    }

    public function setEnabled(int $zoneId, bool $enabled): void
    {
        $model = $this->loadById($zoneId);
        $model->setEnabled($enabled);
        $this->zoneResource->save($model);
        $this->flushCache();
    }

    public function deleteById(int $zoneId): void
    {
        $this->zoneResource->delete($this->loadById($zoneId));
        $this->flushCache();
    }

    private function loadById(int $zoneId): ZoneModel
    {
        $model = $this->zoneFactory->create();
        $this->zoneResource->load($model, $zoneId);
        if (!$model->getId()) {
            throw NoSuchEntityException::singleField('zoneId', $zoneId);
        }

        return $model;
    }

    private function flushCache(): void
    {
        $this->cacheTypeList->cleanType(ZoneCacheType::TYPE_ID);
    }

    /**
     * @param ZoneModel[] $models
     * @return CanonicalZoneInterface[]
     */
    private function voFromRows(array $models): array
    {
        $zones = [];
        foreach ($models as $model) {
            $zones[] = $this->voFromRow($model);
        }
        usort($zones, static function (CanonicalZoneInterface $a, CanonicalZoneInterface $b): int {
            return strcmp($a->getCode(), $b->getCode());
        });

        return $zones;
    }

    private function voFromRow(ZoneModel $model): CanonicalZoneInterface
    {
        return new CanonicalZone(
            $model->getCode(),
            $model->getLabel(),
            $model->isEnabled(),
            $model->getIncludeProvinceCodes(),
            $model->getIncludeWardCodes(),
            $model->getExcludeWardCodes()
        );
    }
}
