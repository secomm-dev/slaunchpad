<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ContextInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Secomm\AddressDropdown\Api\Data\LocationNodeInterface;
use Secomm\AddressDropdown\Api\LocationHierarchyProviderInterface;
use Secomm\AddressDropdown\Api\NoSuchProfileException;
use Secomm\AddressDropdown\Model\Profile\ProfilePool;

/**
 * FEAT-2PZQKJ / TASK-J49PRZ — generic hierarchy listing endpoint.
 *
 * query { addressLocations(input: { region_id: 1185, profile_code: "vn_admin_2025" }) { ... } }
 * query { addressLocations(input: { parent_city_id: 42, profile_code: "vn_admin_2025" }) { ... } }
 *
 * Exactly one of region_id / parent_city_id must be provided. The profile code must be declared
 * in some etc/address_profiles.xml — an undeclared code is a client error, never all-nodes BC.
 * Locale-resolved names come from the store context (resolver area). ID-canonical output.
 */
class AddressLocationsGraphql implements ResolverInterface
{
    public function __construct(
        private readonly LocationHierarchyProviderInterface $hierarchyProvider,
        private readonly ProfilePool $profilePool
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        $input = $args['input'] ?? [];
        $regionId = isset($input['region_id']) ? (int)$input['region_id'] : null;
        $parentCityId = isset($input['parent_city_id']) ? (int)$input['parent_city_id'] : null;
        $profileCode = (string)($input['profile_code'] ?? '');

        if ($profileCode === '') {
            throw new GraphQlInputException(__('"profile_code" is required.'));
        }
        try {
            $this->profilePool->getProfile($profileCode);
        } catch (NoSuchProfileException $exception) {
            throw new GraphQlNoSuchEntityException(__($exception->getMessage()), $exception);
        }
        if (($regionId === null) === ($parentCityId === null)) {
            throw new GraphQlInputException(
                __('Provide exactly one of "region_id" or "parent_city_id".')
            );
        }
        if ($regionId !== null && $regionId < 1 || $parentCityId !== null && $parentCityId < 1) {
            throw new GraphQlInputException(__('Location ids must be positive integers.'));
        }

        try {
            $nodes = $regionId !== null
                ? $this->hierarchyProvider->getRootLocations($regionId, $profileCode)
                : $this->hierarchyProvider->getChildLocations($parentCityId, $profileCode);
        } catch (NoSuchProfileException $exception) {
            throw new GraphQlNoSuchEntityException(__($exception->getMessage()), $exception);
        }

        return array_map(
            static fn (LocationNodeInterface $node): array => [
                'city_id' => $node->getCityId(),
                'default_name' => $node->getDefaultName(),
                'name' => $node->getName(),
                'label' => $node->getName(),
                'depth' => $node->getDepth(),
                'parent_city_id' => $node->getParentCityId(),
                'region_id' => $node->getRegionId(),
                'has_children' => $node->hasChildren(),
            ],
            $nodes
        );
    }
}
