<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\AddressDropdown\Command\City;

use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Api\AddressProfileResolverInterface;
use Secomm\AddressDropdown\Api\AddressSchemaProviderInterface;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Api\HierarchyAddressImportInterface;
use Secomm\AddressDropdown\Api\Data\SchemaLevelInterface;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityCollectionFactory;

/**
 * TASK-9EX975 Slice B — validation for admin city CRUD, mirroring the import rules
 * (HierarchyImportService / HierarchyAddressImportInterface): shared MAX_DEPTH +
 * CODE_COLUMN_LIMIT constants, same-region parents, self/cycle guard, duplicate
 * (region_id, parent_city_id, code) check with a friendly message instead of a raw
 * MySQL unique-index failure.
 *
 * Hard failures throw LocalizedException (rethrown verbatim by SaveCommand so the
 * admin sees the reason); non-blocking findings come back as warnings (AC-B3: a node
 * deeper than the region's resolved profile schema is saved but flagged).
 */
class SaveValidator
{
    public function __construct(
        private readonly CityCollectionFactory $cityCollectionFactory,
        private readonly AddressProfileResolverInterface $profileResolver,
        private readonly AddressSchemaProviderInterface $schemaProvider,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Validate a city before save.
     *
     * @return string[] non-blocking warnings
     * @throws LocalizedException on hard validation failure
     */
    public function validate(CityInterface $city): array
    {
        $regionId = $city->getRegionId();
        if (!$regionId) {
            throw new LocalizedException(__('Region is required.'));
        }

        $parentId = $city->getParentCityId();
        $depth = 1;
        if ($parentId !== null) {
            $depth = $this->resolveDepth($city, (int)$regionId, $parentId);
        }

        $this->assertUniqueCode($city, (int)$regionId);

        return $this->depthCoverageWarnings($city, (int)$regionId, $depth);
    }

    /**
     * Walk the parent chain up to the region root: returns the 1-based depth the node
     * will sit at. Guards against self-parent, cycles and broken chains (bounded by
     * HierarchyAddressImportInterface::MAX_DEPTH — same walk bound as the import).
     *
     * @throws LocalizedException
     */
    private function resolveDepth(CityInterface $city, int $regionId, int $parentId): int
    {
        $selfId = $city->getCityId();
        if ($selfId !== null && $parentId === $selfId) {
            throw new LocalizedException(__('A city cannot be its own parent.'));
        }

        $parent = $this->loadCity($parentId);
        if ($parent === null) {
            throw new LocalizedException(__('Parent city (ID %1) does not exist.', $parentId));
        }
        if ((int)$parent[CityInterface::REGION_ID] !== $regionId) {
            throw new LocalizedException(
                __('Parent city must belong to the same region as the city being saved.')
            );
        }

        $depth = 2; // the node itself + its direct parent
        $current = $parent[CityInterface::PARENT_CITY_ID] !== null
            ? (int)$parent[CityInterface::PARENT_CITY_ID]
            : null;
        $visited = [$parentId => true];

        while ($current !== null) {
            if ($current === $selfId || isset($visited[$current])) {
                throw new LocalizedException(
                    __('Invalid parent: the parent chain loops back into the subtree being moved (cycle).')
                );
            }
            if (count($visited) >= HierarchyAddressImportInterface::MAX_DEPTH) {
                throw new LocalizedException(
                    __('Parent chain exceeds the maximum depth of %1.', HierarchyAddressImportInterface::MAX_DEPTH)
                );
            }
            $visited[$current] = true;

            $ancestor = $this->loadCity($current);
            if ($ancestor === null) {
                throw new LocalizedException(
                    __('Parent chain is broken: city (ID %1) does not exist.', $current)
                );
            }
            if ((int)$ancestor[CityInterface::REGION_ID] !== $regionId) {
                throw new LocalizedException(
                    __('Parent chain crosses into another region (city ID %1).', $current)
                );
            }

            $depth++;
            $current = $ancestor[CityInterface::PARENT_CITY_ID] !== null
                ? (int)$ancestor[CityInterface::PARENT_CITY_ID]
                : null;
        }

        if ($depth > HierarchyAddressImportInterface::MAX_DEPTH) {
            throw new LocalizedException(
                __('Depth %1 exceeds the maximum of %2.', $depth, HierarchyAddressImportInterface::MAX_DEPTH)
            );
        }

        return $depth;
    }

    /**
     * The composite unique index cannot catch duplicates whose code is NULL, and a raw
     * index failure is not admin-friendly — check (region_id, parent_city_id, code)
     * app-level whenever a non-empty code is provided.
     *
     * @throws LocalizedException
     */
    private function assertUniqueCode(CityInterface $city, int $regionId): void
    {
        $code = $city->getCode();
        if ($code === null || trim($code) === '') {
            return;
        }
        if (strlen($code) > HierarchyAddressImportInterface::CODE_COLUMN_LIMIT) {
            throw new LocalizedException(
                __('Code longer than %1 characters.', HierarchyAddressImportInterface::CODE_COLUMN_LIMIT)
            );
        }

        $collection = $this->cityCollectionFactory->create()
            ->addFieldToFilter(CityInterface::REGION_ID, $regionId)
            ->addFieldToFilter(CityInterface::CODE, $code);
        if ($city->getParentCityId() === null) {
            // MySQL treats NULLs as distinct in the composite unique index — enforce depth-1 uniqueness here.
            $collection->addFieldToFilter(CityInterface::PARENT_CITY_ID, ['null' => true]);
        } else {
            $collection->addFieldToFilter(CityInterface::PARENT_CITY_ID, (int)$city->getParentCityId());
        }
        if ($city->getCityId() !== null) {
            $collection->addFieldToFilter(CityInterface::CITY_ID, ['neq' => (int)$city->getCityId()]);
        }

        $existing = $collection->getFirstItem();
        if (!$existing->isEmpty()) {
            throw new LocalizedException(
                __(
                    'A city with code "%1" already exists at this level of the region. Codes must be unique per parent.',
                    $code
                )
            );
        }
    }

    /**
     * AC-B3: a node deeper than the depth its region's resolved profile schema claims is
     * saved (hierarchy stays generic), but the schema renderer will never render it —
     * surface that as a warning (logged; grid flags the row server-side).
     *
     * @return string[]
     */
    private function depthCoverageWarnings(CityInterface $city, int $regionId, int $depth): array
    {
        try {
            $countryId = $this->countryOfRegion($regionId);
            if ($countryId === null) {
                return [];
            }
            $profile = $this->profileResolver->resolve($countryId);
            if ($profile === null) {
                return [];
            }
            $maxCityDepth = 0;
            foreach ($this->schemaProvider->getSchema($profile->getCode()) as $level) {
                if ($level->getEntityType() === SchemaLevelInterface::ENTITY_TYPE_CITY
                    && $level->getDepth() > $maxCityDepth
                ) {
                    $maxCityDepth = $level->getDepth();
                }
            }
            if ($depth > $maxCityDepth) {
                return [sprintf(
                    'City "%s" sits at depth %d, deeper than the %d city level(s) its profile (%s) renders — the schema renderer will not offer it in cascades.',
                    $city->getDefaultName() ?? (string)$city->getCityId(),
                    $depth,
                    $maxCityDepth,
                    $profile->getCode()
                )];
            }
        } catch (\Throwable $e) {
            // Coverage check is best-effort — never block a save on it.
            $this->logger->warning(
                'City depth-coverage check skipped: ' . $e->getMessage(),
                ['city_id' => $city->getCityId(), 'region_id' => $regionId]
            );
        }

        return [];
    }

    private function loadCity(int $cityId): ?array
    {
        $item = $this->cityCollectionFactory->create()
            ->addFieldToFilter(CityInterface::CITY_ID, $cityId)
            ->getFirstItem();

        return $item->isEmpty() ? null : $item->getData();
    }

    private function countryOfRegion(int $regionId): ?string
    {
        $connection = $this->cityCollectionFactory->create()->getConnection();
        $value = $connection->fetchOne(
            $connection->select()
                ->from('directory_country_region', ['country_id'])
                ->where('region_id = ?', $regionId)
        );

        return $value === false || $value === null ? null : (string)$value;
    }
}
