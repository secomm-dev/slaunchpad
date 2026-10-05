<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Resolver;

use Magento\Framework\App\Area;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Secomm\AddressDropdown\Api\AddressProfileResolverInterface;
use Secomm\AddressDropdown\Api\Data\LocationNodeInterface;
use Secomm\AddressDropdown\Api\LocationHierarchyProviderInterface;
use Secomm\AddressDropdown\Model\DataStorage;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Secomm\AddressDropdown\Helper\Data;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollectionFactory as CityCollectionFactory;

/**
 * TASK-Z6SK3T / DEC-TASKZ6SK3T-001 — BC shim over the canonical hierarchy engine.
 *
 * The field stays `@deprecated` with an unchanged input/output contract (schema.graphqls
 * untouched — no graphql:dump needed). For a mapped country the resolver delegates to
 * LocationHierarchyProvider::getRootLocations() (root cities only — the shim MUST NOT
 * reproduce the legacy flat mixed-tier list on 3-level profiles, TASK-6MKF0V AC-3).
 * Unmapped countries and admin-area callers keep the legacy CityLocaleCollection path
 * (BC for non-profile datasets and the admin DEFAULT_LOCALE rule).
 */
class GetListCityGraphql implements ResolverInterface
{

    public function __construct(
        protected CityCollectionFactory $cityCollectionFactory,
        protected DataStorage $dataStorage,
        protected ScopeConfigInterface $scopeConfig,
        protected Data $helper,
        protected AddressProfileResolverInterface $profileResolver,
        protected LocationHierarchyProviderInterface $hierarchyProvider
    )
    {
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlNoSuchEntityException
     */
    public function resolve(
        Field       $field,
                    $context,
        ResolveInfo $info,
        array       $value = null,
        array       $args = null)
    {
        // TASK-SEC-A5: master switch off for this store scope — no AddressDropdown data.
        if (!$this->scopeConfig->isSetFlag(Data::XML_PATH_ADDRESS, \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) {
            return [];
        }

        try {
            $isAdminArea = isset($args['input']['area']) && $args['input']['area'] === Area::AREA_ADMINHTML;
            if ($isAdminArea) {
                $this->dataStorage->set('area', $args['input']['area']);
            }

            $regionId = $args['input']['region_id'] ?? null;
            if (!$isAdminArea) {
                $shimmed = $this->resolveViaHierarchy($regionId);
                if ($shimmed !== null) {
                    return $shimmed;
                }
            }

            // Legacy path: unmapped country, admin area (DEFAULT_LOCALE rule), or an
            // unknown region id — canonical ordering is owned by
            // CityLocaleCollection::_initSelect (TASK-7HVGAB).
            $output = [];
            $cityCollection = $this->cityCollectionFactory->create();
            $cityCollection->addFieldToSelect('*');
            if ($regionId !== null) {
                $cityCollection->addFieldToFilter('region_id', $regionId);
            }
            $cityCollection->load();
            foreach ($cityCollection as $city) {
                $output[] = [
                    'city_id' => $city->getCityId(),
                    'region_id' => $city->getRegionId(),
                    'label' => $city->getName() ?? $city->getDefaultName(),
                    'default_name' => $city->getDefaultName(),
                ];
            }
            return $output;
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(__($e->getMessage()), $e);
        }
    }

    /**
     * Canonical fast path (DEC-TASKZ6SK3T-001). Returns the legacy-shaped row list for a
     * profile-mapped country, or null to fall through to the legacy collection path.
     *
     * @param mixed $regionId string|int|null from the GraphQL input
     * @return array|null
     */
    private function resolveViaHierarchy(mixed $regionId): ?array
    {
        if ($regionId === null) {
            return null;
        }

        $intRegionId = (int) $regionId;
        if ($intRegionId <= 0 || (string) $intRegionId !== (string) $regionId) {
            // Non-numeric / non-positive input: the legacy collection filtered to an empty
            // result for these — return the same shape without the query.
            return [];
        }

        $countryId = $this->helper->getCountryIdByRegionId($intRegionId);
        if ($countryId === '') {
            // Unknown region: legacy returned an empty list as well.
            return [];
        }

        $profile = $this->profileResolver->resolve($countryId);
        if ($profile === null) {
            // Country unmapped in address/profiles/mapping — keep the legacy dataset path.
            return null;
        }

        // Root cities only (parent_city_id IS NULL semantics of getRootLocations).
        $nodes = $this->hierarchyProvider->getRootLocations($intRegionId, $profile->getCode());

        return array_map(
            static fn (LocationNodeInterface $node): array => [
                'city_id' => $node->getCityId(),
                'region_id' => $node->getRegionId(),
                'label' => $node->getName() ?: $node->getDefaultName(),
                'default_name' => $node->getDefaultName(),
            ],
            $nodes
        );
    }
}
